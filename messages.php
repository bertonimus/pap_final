<?php
session_start();

if (!isset($_SESSION['id_utilizadores']) || !isset($_SESSION['utilizador'])) {
    header("Location: logintexte.php");
    exit();
}

require_once 'escrow_system.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestao_utilizadores";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$escrow_system = new EscrowSystem($conn);
$user_id = $_SESSION['id_utilizadores'];
$nome_usuario = $_SESSION['utilizador'];

// Processar envio de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_mensagem'])) {
    $destinatario_id = (int)$_POST['destinatario_id'];
    $mensagem = trim($_POST['mensagem']);
    $servico_id = isset($_POST['servico_id']) ? (int)$_POST['servico_id'] : null;
    
    if (!empty($mensagem)) {
        $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, servico_id, data_envio) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisi", $user_id, $destinatario_id, $mensagem, $servico_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Processar criação de oferta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_oferta'])) {
    $destinatario_id = (int)$_POST['destinatario_id'];
    $valor = (float)$_POST['valor'];
    $servico_id = isset($_POST['servico_id']) ? (int)$_POST['servico_id'] : null;
    
    if ($valor > 0) {
        $stmt = $conn->prepare("INSERT INTO ofertas (remetente_id, destinatario_id, valor, status) VALUES (?, ?, ?, 'pendente')");
        $stmt->bind_param("iid", $user_id, $destinatario_id, $valor);
        $stmt->execute();
        $offer_id = $conn->insert_id;
        $stmt->close();
        
        // Enviar mensagem automática sobre a oferta
        $mensagem_oferta = "💰 Nova oferta: €" . number_format($valor, 2) . " - Aguardando resposta";
        $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, servico_id, data_envio, tipo) VALUES (?, ?, ?, ?, NOW(), 'oferta')");
        $stmt->bind_param("iisi", $user_id, $destinatario_id, $mensagem_oferta, $servico_id);
        $stmt->execute();
        $stmt->close();
        header("Location: ".$_SERVER['PHP_SELF']);
    exit();
    }
}

// Processar resposta à oferta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_oferta'])) {
    $offer_id = (int)$_POST['offer_id'];
    $resposta = $_POST['responder_oferta']; // 'aceitar' ou 'rejeitar' - CORRIGIDO
    
    // Buscar dados da oferta
    $stmt = $conn->prepare("
        SELECT o.*, s.id_servico, s.id_utilizador as service_creator_id, s.nome as service_name
        FROM ofertas o
        LEFT JOIN mensagens m ON (
            (m.remetente_id = o.remetente_id AND m.destinatario_id = o.destinatario_id) OR
            (m.remetente_id = o.destinatario_id AND m.destinatario_id = o.remetente_id)
        )
        LEFT JOIN servicos s ON m.servico_id = s.id_servico
        WHERE o.id = ? AND o.destinatario_id = ? AND o.status = 'pendente'
        LIMIT 1
    ");
    $stmt->bind_param("ii", $offer_id, $user_id);
    $stmt->execute();
    $offer_result = $stmt->get_result();
    $offer = $offer_result->fetch_assoc();
    $stmt->close();

    if ($offer) {
        if ($resposta === 'aceitar') {
            // Atualizar status da oferta
            $stmt = $conn->prepare("UPDATE ofertas SET status = 'aceita' WHERE id = ?");
            $stmt->bind_param("i", $offer_id);
            $stmt->execute();
            $stmt->close();
            
            // Determinar quem é cliente e quem é prestador
            $service_creator_id = $offer['service_creator_id'];
            $offer_sender = $offer['remetente_id'];
            $offer_receiver = $offer['destinatario_id'];
            
            if ($service_creator_id) {
                // O cliente é quem criou o serviço
                $client_id = $service_creator_id;
                $provider_id = ($service_creator_id == $offer_sender) ? $offer_receiver : $offer_sender;
            } else {
                // Fallback: assumir que quem aceita é o prestador
                $client_id = $offer_sender;
                $provider_id = $offer_receiver;
            }
            
            // Verificar se a tabela accepted_offers existe
            $table_check = $conn->query("SHOW TABLES LIKE 'accepted_offers'");
            if ($table_check->num_rows > 0) {
                // Inserir na tabela accepted_offers
                $stmt = $conn->prepare("
                    INSERT INTO accepted_offers (offer_id, client_id, provider_id, service_id, service_name) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        client_id = VALUES(client_id), 
                        provider_id = VALUES(provider_id),
                        service_id = VALUES(service_id),
                        service_name = VALUES(service_name)
                ");
                $stmt->bind_param("iiiis", $offer_id, $client_id, $provider_id, $offer['id_servico'], $offer['service_name']);
                $stmt->execute();
                $stmt->close();
            }
            
            // Mensagens de confirmação
            $mensagem_aceitacao = "✅ Oferta aceita: €" . number_format($offer['valor'], 2) . " - Você precisa efetuar o pagamento para confirmar o serviço.";
            $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, servico_id, data_envio, tipo) VALUES (?, ?, ?, ?,  NOW(), 'sistema')");
            $stmt->bind_param("iisi", $user_id, $offer['remetente_id'], $mensagem_aceitacao,$offer['id_servico']);
            $stmt->execute();
            $stmt->close();
            
            $mensagem_prestador = "✅ Você aceitou a oferta de €" . number_format($offer['valor'], 2) . " - Aguardando pagamento do cliente.";
            $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, servico_id, data_envio, tipo) VALUES (?, ?, ?, ?, NOW(), 'sistema')");
            $stmt->bind_param("iisi", $offer['remetente_id'], $user_id, $mensagem_prestador,$offer['id_servico']);
            $stmt->execute();
            $stmt->close();
            
        } elseif ($resposta === 'rejeitar') {
            // Atualizar status da oferta
            $stmt = $conn->prepare("UPDATE ofertas SET status = 'rejeitada' WHERE id = ?");
            $stmt->bind_param("i", $offer_id);
            $stmt->execute();
            $stmt->close();
            
            // Enviar mensagem de rejeição
            $mensagem_rejeicao = "❌ Oferta rejeitada - Pode fazer uma nova proposta";
            $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, servico_id, data_envio, tipo) VALUES (?, ?, ?, NOW(), 'sistema')");
            $stmt->bind_param("iisi", $user_id, $offer['remetente_id'], $mensagem_rejeicao, $offer['id_servico']);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Obter lista de conversas
$stmt = $conn->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN m.remetente_id = ? THEN m.destinatario_id 
            ELSE m.remetente_id 
        END as contact_id,
        u.utilizador as contact_name,
        s.nome as service_name,
        s.id_servico as service_id,
        MAX(m.data_envio) as last_message_date
    FROM mensagens m
    JOIN utilizadores u ON (
        CASE 
            WHEN m.remetente_id = ? THEN m.destinatario_id = u.id_utilizadores
            ELSE m.remetente_id = u.id_utilizadores
        END
    )
    LEFT JOIN servicos s ON m.servico_id = s.id_servico
    WHERE m.remetente_id = ? OR m.destinatario_id = ?
    GROUP BY contact_id, service_id
    ORDER BY last_message_date DESC
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result();
$stmt->close();

// Obter mensagens da conversa selecionada
$selected_contact = isset($_GET['destinatario_id']) ? (int)$_GET['destinatario_id'] : null;
$selected_service = isset($_GET['servico_id']) ? (int)$_GET['servico_id'] : null;
$messages = [];
$contact_name = '';

if ($selected_contact) {
    // Buscar nome do contato
    $stmt = $conn->prepare("SELECT utilizador FROM utilizadores WHERE id_utilizadores = ?");
    $stmt->bind_param("i", $selected_contact);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $contact_name = $row['utilizador'];
    }
    $stmt->close();
    
    // Buscar mensagens
    $where_clause = "WHERE ((m.remetente_id = ? AND m.destinatario_id = ?) OR (m.remetente_id = ? AND m.destinatario_id = ?))";
    $params = [$user_id, $selected_contact, $selected_contact, $user_id];
    $types = "iiii";
    
    if ($selected_service) {
        $where_clause .= " AND (m.servico_id = ? OR m.servico_id IS NULL)";
        $params[] = $selected_service;
        $types .= "i";
    }
    
    $stmt = $conn->prepare("
        SELECT m.*, u.utilizador as remetente_nome 
        FROM mensagens m
        JOIN utilizadores u ON m.remetente_id = u.id_utilizadores
        $where_clause
        ORDER BY m.data_envio ASC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $messages_result = $stmt->get_result();
    
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
}

// Obter ofertas pendentes para o usuário atual
$stmt = $conn->prepare("
    SELECT o.*, u.utilizador as remetente_nome 
    FROM ofertas o
    JOIN utilizadores u ON o.remetente_id = u.id_utilizadores
    WHERE o.destinatario_id = ? AND o.status = 'pendente'
    ORDER BY o.data_criacao DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_offers = $stmt->get_result();

// Obter ofertas aceitas que precisam de pagamento
$stmt = $conn->prepare("
    SELECT ao.*, o.valor, u.utilizador as provider_name, ao.service_name
    FROM accepted_offers ao
    JOIN ofertas o ON ao.offer_id = o.id
    JOIN utilizadores u ON ao.provider_id = u.id_utilizadores
    WHERE ao.client_id = ? AND o.status = 'aceita'
    ORDER BY ao.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment_needed = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Mensagens</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link rel="icon" type="image/png" href="../berto.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />
    <style>
        :root {
            --primary-color: #059669;
            --primary-hover: #047857;
            --primary-light: #10b981;
            --secondary-color: #6b7280;
            --background-color: #fafafa;
            --card-background: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --border-hover: #d1d5db;
            --shadow-soft: 0 2px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 4px 25px rgba(0, 0, 0, 0.12);
            --shadow-strong: 0 8px 40px rgba(0, 0, 0, 0.15);
            --shadow-glow: 0 0 20px rgba(16, 185, 129, 0.3);
            --transition-speed: 0.3s;
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-orange: #f59e0b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            line-height: 1.6;
            font-weight: 400;
        }

        /* Navbar Styles */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-soft);
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .navbar h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            transition: color var(--transition-speed);
        }

        .navbar h1:hover {
            color: var(--primary-hover);
        }

        .navbar-list {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
            padding: 0;
        }

        .navbar-list > li {
            position: relative;
        }

        .navbar-list a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            transition: all var(--transition-speed);
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-list a:hover {
            color: var(--primary-color);
            background-color: rgba(16, 185, 129, 0.1);
        }

        .navbar-list a.active {
            color: var(--primary-color);
            background-color: rgba(16, 185, 129, 0.1);
            font-weight: 600;
        }

        /* Messages Dropdown */
        .messages-dropdown {
            position: relative;
        }

        .messages-dropdown-list {
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 0;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-strong);
            min-width: 200px;
            list-style: none;
            padding: 0.5rem 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all var(--transition-speed);
        }

        .messages-dropdown:hover .messages-dropdown-list {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .messages-dropdown-list li a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-speed);
            border-radius: 0;
        }

        .messages-dropdown-list li a:hover {
            background-color: rgba(16, 185, 129, 0.05);
            color: var(--primary-color);
        }

        .messages-dropdown-list li a.services {
            border-left: 3px solid var(--primary-color);
        }

        .messages-dropdown-list li a.products {
            border-left: 3px solid var(--accent-orange);
        }

        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
        }

        .profile-dropdown-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all var(--transition-speed);
            font-weight: 500;
            color: var(--text-primary);
        }

        .profile-dropdown-btn:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-soft);
        }

        .profile-img {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-img i {
            color: white;
            font-size: 0.875rem;
        }

        .profile-dropdown-list {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-strong);
            min-width: 220px;
            list-style: none;
            padding: 0.5rem 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all var(--transition-speed);
        }

        .profile-dropdown.active .profile-dropdown-list {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-dropdown-list-item a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-speed);
        }

        .profile-dropdown-list-item a:hover {
            background-color: rgba(16, 185, 129, 0.05);
            color: var(--primary-color);
        }

        .profile-dropdown-list hr {
            margin: 0.5rem 0;
            border: none;
            border-top: 1px solid var(--border-color);
        }

        /* Main Layout */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 300px 1fr 250px;
            gap: 2rem;
            height: calc(100vh - 120px);
        }

        /* Sidebar */
        .sidebar {
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .sidebar-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem 0;
        }

        .conversation-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .conversation-item:hover {
            background-color: rgba(16, 185, 129, 0.05);
        }

        .conversation-item.active {
            background-color: rgba(16, 185, 129, 0.1);
            border-right: 3px solid var(--primary-color);
        }

        .conversation-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .conversation-service {
            font-size: 0.875rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Chat Area */
        .chat-container {
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--card-background);
        }

        .chat-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background: #f8fafc;
            max-height: 500px;
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .message.own {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .message.own .message-avatar {
            background: linear-gradient(135deg, var(--accent-blue), #60a5fa);
        }

        .message-content {
            max-width: 70%;
            background: white;
            padding: 0.875rem 1.125rem;
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            position: relative;
        }

        .message.own .message-content {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .message-text {
            font-size: 0.9rem;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            opacity: 0.7;
        }

        .message.own .message-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .message.system .message-content {
            background: #fef3c7;
            border-color: #fbbf24;
            color: #92400e;
            font-style: italic;
            text-align: center;
            max-width: 100%;
        }

        .message.offer .message-content {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1e40af;
            font-weight: 600;
        }

        /* Chat Input */
        .chat-input {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            background: var(--card-background);
        }

        .input-form {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .input-form textarea {
            flex: 1;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            resize: none;
            font-family: inherit;
            font-size: 0.9rem;
            line-height: 1.4;
            max-height: 100px;
            transition: all var(--transition-speed);
        }

        .input-form textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .send-btn {
            padding: 0.875rem 1.25rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all var(--transition-speed);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Offers Panel */
        .offers-panel {
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: 100%;
        }

        .offers-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--accent-orange), #fbbf24);
            color: white;
        }

        .offers-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .offers-content {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .offer-section {
            margin-bottom: 2rem;
        }

        .offer-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .offer-item {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all var(--transition-speed);
        }

        .offer-item:hover {
            box-shadow: var(--shadow-soft);
            border-color: var(--primary-color);
        }

        .offer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .offer-amount {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .offer-from {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .offer-actions {
            display: flex;
            gap: 0.5rem;
        }

        .offer-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .offer-btn.accept {
            background: var(--success-color);
            color: white;
        }

        .offer-btn.reject {
            background: var(--error-color);
            color: white;
        }

        .offer-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-soft);
        }

        .payment-btn {
            background: linear-gradient(135deg, var(--accent-blue), #60a5fa);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Offer Form */
        .offer-form {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .offer-form h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all var(--transition-speed);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .offer-submit-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .offer-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .empty-state small {
            font-size: 0.875rem;
            opacity: 0.7;
        }

        /* Scrollbar Styling */
        .conversations-list::-webkit-scrollbar,
        .chat-messages::-webkit-scrollbar,
        .offers-content::-webkit-scrollbar {
            width: 6px;
        }

        .conversations-list::-webkit-scrollbar-track,
        .chat-messages::-webkit-scrollbar-track,
        .offers-content::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .conversations-list::-webkit-scrollbar-thumb,
        .chat-messages::-webkit-scrollbar-thumb,
        .offers-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .conversations-list::-webkit-scrollbar-thumb:hover,
        .chat-messages::-webkit-scrollbar-thumb:hover,
        .offers-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-container {
                grid-template-columns: 280px 1fr 240px;
            }
        }

        @media (max-width: 768px) {
            .navbar-container {
                padding: 1rem;
            }

            .navbar-list {
                display: none;
            }

            .main-container {
                grid-template-columns: 1fr;
                padding: 1rem;
                height: auto;
            }

            .sidebar,
            .offers-panel {
                display: none;
            }

            .chat-container {
                height: 70vh;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <h1>Berto</h1>
            <ul class="navbar-list">
                <li><a href="index.php">Início</a></li>
                <li><a href="produtos.php">Produtos</a></li>
                <li><a href="servicos_resultados.php">Serviços</a></li>
                <li><a href="suporte.php">Suporte</a></li>
                <li class="messages-dropdown">
                    <a href="#" class="active">
                        Mensagens
                        <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
                    </a>
                    <ul class="messages-dropdown-list">
                        <li><a href="messages.php" class="services"><i class="fa-solid fa-cog"></i>Serviços</a></li>
                        <li><a href="product_messages.php" class="products"><i class="fa-solid fa-box"></i>Produtos</a></li>
                    </ul>
                </li>
                <li><a href="#">Sobre</a></li>
            </ul>

            <div class="profile-dropdown">
                <div onclick="toggle()" class="profile-dropdown-btn">
                    <div class="profile-img">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span>
                        <?php echo htmlspecialchars($nome_usuario); ?>
                        <i class="fa-solid fa-chevron-down" style="margin-left: 0.5rem; font-size: 0.75rem;"></i>
                    </span>
                </div>

                <ul class="profile-dropdown-list">
                    <li class="profile-dropdown-list-item">
                        <a href="utilizador/profile/index.php">
                            <i class="fa-regular fa-user"></i>
                            Editar Perfil
                        </a>
                    </li>
                    <li class="profile-dropdown-list-item">
                        <a href="delivery_proof.php">
                            <i class="fa-solid fa-truck"></i>
                            Provas de Entrega
                        </a>
                    </li>
                    <li class="profile-dropdown-list-item">
                        <a href="user_balance.php">
                            <i class="fa-solid fa-wallet"></i>
                            Meu Saldo
                        </a>
                    </li>
                    <hr />
                    <li class="profile-dropdown-list-item">
                        <form id="logout-form" action="utilizador/logout.php" method="POST">
                            <input type="hidden" name="botaoLogout">
                            <a href="#" onclick="document.getElementById('logout-form').submit();">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                Sair
                            </a>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar - Conversations -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>
                    <i class="fas fa-comments"></i>
                    Conversas
                </h3>
            </div>
            <div class="conversations-list">
                <?php if ($conversations->num_rows > 0): ?>
                    <?php while ($conv = $conversations->fetch_assoc()): ?>
                        <div class="conversation-item <?= ($selected_contact == $conv['contact_id'] && $selected_service == $conv['service_id']) ? 'active' : '' ?>" 
                             onclick="window.location.href='?destinatario_id=<?= $conv['contact_id'] ?>&servico_id=<?= $conv['service_id'] ?>'">
                            <div class="conversation-avatar">
                                <?= strtoupper(substr($conv['contact_name'], 0, 2)) ?>
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-name"><?= htmlspecialchars($conv['contact_name']) ?></div>
                                <div class="conversation-service">
                                    <?= $conv['service_name'] ? htmlspecialchars($conv['service_name']) : 'Conversa geral' ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Nenhuma conversa</p>
                        <small>Suas conversas aparecerão aqui</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-container">
            <?php if ($selected_contact): ?>
                <div class="chat-header">
                    <h3>
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($contact_name) ?>
                    </h3>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?= $msg['remetente_id'] == $user_id ? 'own' : '' ?> <?= $msg['tipo'] ?>">
                                <div class="message-avatar">
                                    <?= strtoupper(substr($msg['remetente_nome'], 0, 2)) ?>
                                </div>
                                <div class="message-content">
                                    <div class="message-text"><?= nl2br(htmlspecialchars($msg['mensagem'])) ?></div>
                                    <div class="message-time"><?= date('H:i', strtotime($msg['data_envio'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-comment-dots"></i>
                            <p>Nenhuma mensagem ainda</p>
                            <small>Inicie a conversa enviando uma mensagem</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chat-input">
                    <form method="POST" class="input-form" onsubmit="return validateMessage()">
                        <input type="hidden" name="destinatario_id" value="<?= $selected_contact ?>">
                        <input type="hidden" name="servico_id" value="<?= $selected_service ?>">
                        <textarea name="mensagem" placeholder="Digite sua mensagem..." rows="1" required></textarea>
                        <button type="submit" name="enviar_mensagem" class="send-btn">
                            <i class="fas fa-paper-plane"></i>
                            Enviar
                        </button>
                    </form>

                    <?php if ($selected_contact): ?>
                        <div class="offer-form">
                            
                            <form method="POST" onsubmit="return validateOffer()">
                                <input type="hidden" name="destinatario_id" value="<?= $selected_contact ?>">
                                <input type="hidden" name="servico_id" value="<?= $selected_service ?>">
                                <div class="form-group">
                                    <input type="number" name="valor" step="0.01" min="1" placeholder="Valor da Oferta 0.00" required>
                                </div>
                                <button type="submit" name="criar_oferta" class="offer-submit-btn">
                                    <i class="fas fa-euro-sign"></i>
                                    Enviar Oferta
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>Selecione uma conversa</p>
                    <small>Escolha uma conversa da lista para começar a conversar</small>
                </div>
            <?php endif; ?>
        </div>

        <!-- Offers Panel -->
        <div class="offers-panel">
            <div class="offers-header">
                <h3>
                    <i class="fas fa-handshake"></i>
                    Ofertas
                </h3>
            </div>
            <div class="offers-content">
                <!-- Pending Offers -->
                <div class="offer-section">
                    <h4>
                        <i class="fas fa-clock"></i>
                        Ofertas Pendentes
                    </h4>
                    <?php if ($pending_offers->num_rows > 0): ?>
                        <?php while ($offer = $pending_offers->fetch_assoc()): ?>
                            <div class="offer-item">
                                <div class="offer-header">
                                    <div class="offer-amount">€<?= number_format($offer['valor'], 2) ?></div>
                                    <div class="offer-from">de <?= htmlspecialchars($offer['remetente_nome']) ?></div>
                                </div>
                                <div class="offer-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                        <button type="submit" name="responder_oferta" value="aceitar" class="offer-btn accept" onclick="return confirm('Aceitar esta oferta?')">
                                            <i class="fas fa-check"></i> Aceitar
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                        <button type="submit" name="responder_oferta" value="rejeitar" class="offer-btn reject" onclick="return confirm('Rejeitar esta oferta?')">
                                            <i class="fas fa-times"></i> Rejeitar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhuma oferta pendente</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Needed -->
                <div class="offer-section">
                    <h4>
                        <i class="fas fa-credit-card"></i>
                        Pagamentos Pendentes
                    </h4>
                    <?php if ($payment_needed->num_rows > 0): ?>
                        <?php while ($payment = $payment_needed->fetch_assoc()): ?>
                            <div class="offer-item">
                                <div class="offer-header">
                                    <div class="offer-amount">€<?= number_format($payment['valor'], 2) ?></div>
                                    <div class="offer-from">para <?= htmlspecialchars($payment['provider_name']) ?></div>
                                </div>
                                <?php if ($payment['service_name']): ?>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.75rem;">
                                        Serviço: <?= htmlspecialchars($payment['service_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <button onclick="window.location.href='checkout.php?offer_id=<?= $payment['offer_id'] ?>'" class="payment-btn">
                                    <i class="fas fa-credit-card"></i>
                                    Efetuar Pagamento
                                </button>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Nenhum pagamento pendente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggle() {
            document.querySelector('.profile-dropdown').classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.profile-dropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        function validateMessage() {
            const textarea = document.querySelector('textarea[name="mensagem"]');
            if (textarea.value.trim() === '') {
                alert('Por favor, digite uma mensagem');
                return false;
            }
            return true;
        }

        function validateOffer() {
            const valor = document.querySelector('input[name="valor"]');
            if (parseFloat(valor.value) <= 0) {
                alert('Por favor, insira um valor válido');
                return false;
            }
            return confirm('Confirma o envio desta oferta?');
        }

        // Auto-scroll to bottom of chat
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Auto-resize textarea
        document.querySelector('textarea[name="mensagem"]').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Auto-refresh messages every 30 seconds
        setInterval(function() {
            if (window.location.search) {
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>