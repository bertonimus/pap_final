<?php
session_start();

if (!isset($_SESSION['id_utilizadores'])) {
    header("Location: logintexte.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestao_utilizadores";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['id_utilizadores'];
$nome_usuario = $_SESSION['utilizador'];

// Verificar mensagens da sessão (similar ao sistema de serviços)
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Processar envio de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $destinatario_id = (int)$_POST['destinatario_id'];
    $mensagem = trim($_POST['mensagem']);
    $produto_id = isset($_POST['produto_id']) ? (int)$_POST['produto_id'] : null;
    
    if (!empty($mensagem) && $destinatario_id > 0) {
        try {
            $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, produto_id, data_envio) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisi", $user_id, $destinatario_id, $mensagem, $produto_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = "✅ Mensagem enviada com sucesso!";
            
            // Redirecionar para evitar reenvio do formulário
            $redirect_url = "product_messages.php?destinatario_id=" . $destinatario_id;
            if ($produto_id) {
                $redirect_url .= "&produto_id=" . $produto_id;
            }
            header("Location: " . $redirect_url);
            exit();
            
        } catch (Exception $e) {
            $error_message = "❌ Erro ao enviar mensagem: " . $e->getMessage();
        }
    }
}

// Processar envio de oferta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_offer'])) {
    $destinatario_id = (int)$_POST['destinatario_id'];
    $produto_id = (int)$_POST['produto_id'];
    $valor = (float)$_POST['valor'];
    $quantidade = (int)$_POST['quantidade'];
    
    if ($valor > 0 && $destinatario_id > 0 && $produto_id > 0 && $quantidade > 0) {
        // VERIFICAR SE JÁ EXISTE OFERTA PENDENTE PARA ESTE PRODUTO
        $stmt = $conn->prepare("
            SELECT COUNT(*) as offer_count 
            FROM ofertas 
            WHERE remetente_id = ? AND destinatario_id = ? AND produto_id = ? AND status = 'pendente' AND tipo = 'produto'
        ");
        $stmt->bind_param("iii", $user_id, $destinatario_id, $produto_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $offer_check = $result->fetch_assoc();
        $stmt->close();
        
        if ($offer_check['offer_count'] > 0) {
            // JÁ EXISTE OFERTA PENDENTE
            $_SESSION['error_message'] = "❌ Você já tem uma oferta pendente para este produto. Aguarde a resposta antes de fazer uma nova oferta.";
        } else {
            // Verificar se há quantidade suficiente
            $stmt = $conn->prepare("SELECT quantidade, nome FROM produtos WHERE id = ?");
            $stmt->bind_param("i", $produto_id);
            $stmt->execute();
            $product_result = $stmt->get_result();
            $product = $product_result->fetch_assoc();
            $stmt->close();
            
            if (!$product) {
                $_SESSION['error_message'] = "❌ Produto não encontrado.";
            } elseif ($product['quantidade'] < $quantidade) {
                $_SESSION['error_message'] = "❌ Quantidade insuficiente em estoque. Disponível: " . $product['quantidade'];
            } else {
                // Iniciar transação para criar nova oferta
                $conn->begin_transaction();
                
                try {
                    // CRIAR NOVA OFERTA
                    $stmt = $conn->prepare("INSERT INTO ofertas (remetente_id, destinatario_id, produto_id, valor, quantidade, status, tipo, data_criacao) VALUES (?, ?, ?, ?, ?, 'pendente', 'produto', NOW())");
                    $stmt->bind_param("iiidi", $user_id, $destinatario_id, $produto_id, $valor, $quantidade);
                    $stmt->execute();
                    $offer_id = $conn->insert_id;
                    $stmt->close();
                    
                    // Enviar mensagem automática sobre a oferta
                    $valor_total = $valor * $quantidade;
                    $mensagem_oferta = "💰 Nova oferta para {$quantidade}x {$product['nome']}: €" . number_format($valor, 2) . " cada (Total: €" . number_format($valor_total, 2) . ") - Aguardando resposta";
                    $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, produto_id, data_envio, tipo) VALUES (?, ?, ?, ?, NOW(), 'oferta')");
                    $stmt->bind_param("iisi", $user_id, $destinatario_id, $mensagem_oferta, $produto_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Confirmar transação
                    $conn->commit();
                    
                    $_SESSION['success_message'] = "✅ Oferta enviada com sucesso!";
                    
                    // Redirecionar para evitar reenvio do formulário
                    $redirect_url = "product_messages.php?destinatario_id=" . $destinatario_id;
                    if ($produto_id) {
                        $redirect_url .= "&produto_id=" . $produto_id;
                    }
                    header("Location: " . $redirect_url);
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error_message'] = "❌ Erro ao enviar oferta: " . $e->getMessage();
                }
            }
        }
        
        // Redirecionar para evitar reenvio do formulário
        $redirect_url = "product_messages.php?destinatario_id=" . $destinatario_id;
        if ($produto_id) {
            $redirect_url .= "&produto_id=" . $produto_id;
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// Obter ID do destinatário e produto da URL
$destinatario_id = isset($_GET['destinatario_id']) ? (int)$_GET['destinatario_id'] : 0;
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : null;

// Buscar conversas do usuário AGRUPADAS POR PRODUTO
$stmt = $conn->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN m.remetente_id = ? THEN m.destinatario_id 
            ELSE m.remetente_id 
        END as contact_id,
        m.produto_id,
        u.utilizador as contact_name,
        p.nome as product_name,
        p.quantidade as product_quantity,
        MAX(m.data_envio) as last_message_time,
        (SELECT mensagem FROM mensagens m2 
         WHERE ((m2.remetente_id = ? AND m2.destinatario_id = contact_id) 
            OR (m2.remetente_id = contact_id AND m2.destinatario_id = ?))
         AND (m2.produto_id = m.produto_id OR (m2.produto_id IS NULL AND m.produto_id IS NULL))
         ORDER BY m2.data_envio DESC LIMIT 1) as last_message,
        COUNT(DISTINCT m.id) as message_count
    FROM mensagens m
    JOIN utilizadores u ON u.id_utilizadores = CASE 
        WHEN m.remetente_id = ? THEN m.destinatario_id 
        ELSE m.remetente_id 
    END
    LEFT JOIN produtos p ON m.produto_id = p.id
    WHERE (m.remetente_id = ? OR m.destinatario_id = ?) AND m.produto_id IS NOT NULL
    GROUP BY contact_id, m.produto_id, u.utilizador, p.nome, p.quantidade
    ORDER BY last_message_time DESC
");
$stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result();

// Se há um destinatário específico, buscar mensagens
$messages = [];
$contact_name = '';
$product_name = '';
$product_quantity = 0;
if ($destinatario_id > 0) {
    // Buscar nome do contato
    $stmt = $conn->prepare("SELECT utilizador FROM utilizadores WHERE id_utilizadores = ?");
    $stmt->bind_param("i", $destinatario_id);
    $stmt->execute();
    $contact_result = $stmt->get_result();
    if ($contact_row = $contact_result->fetch_assoc()) {
        $contact_name = $contact_row['utilizador'];
    }
    
    // Buscar nome do produto se especificado
    if ($produto_id) {
        $stmt = $conn->prepare("SELECT nome, quantidade FROM produtos WHERE id = ?");
        $stmt->bind_param("i", $produto_id);
        $stmt->execute();
        $product_result = $stmt->get_result();
        if ($product_row = $product_result->fetch_assoc()) {
            $product_name = $product_row['nome'];
            $product_quantity = $product_row['quantidade'];
        }
    }
    
    // Buscar mensagens da conversa específica (incluindo filtro por produto)
    if ($produto_id) {
        $stmt = $conn->prepare("
            SELECT m.*, u.utilizador as remetente_nome 
            FROM mensagens m
            JOIN utilizadores u ON m.remetente_id = u.id_utilizadores
            WHERE ((m.remetente_id = ? AND m.destinatario_id = ?) 
               OR (m.remetente_id = ? AND m.destinatario_id = ?))
            AND (m.produto_id = ? OR m.produto_id IS NULL)
            ORDER BY m.data_envio ASC
        ");
        $stmt->bind_param("iiiii", $user_id, $destinatario_id, $destinatario_id, $user_id, $produto_id);
    } else {
        $stmt = $conn->prepare("
            SELECT m.*, u.utilizador as remetente_nome 
            FROM mensagens m
            JOIN utilizadores u ON m.remetente_id = u.id_utilizadores
            WHERE ((m.remetente_id = ? AND m.destinatario_id = ?) 
               OR (m.remetente_id = ? AND m.destinatario_id = ?))
            AND m.produto_id IS NULL
            ORDER BY m.data_envio ASC
        ");
        $stmt->bind_param("iiii", $user_id, $destinatario_id, $destinatario_id, $user_id);
    }
    $stmt->execute();
    $messages = $stmt->get_result();
}

// Verificar se o usuário atual pode fazer oferta para o destinatário selecionado (PRODUTOS)
$can_make_offer = true;
$existing_offer_message = '';
if ($destinatario_id > 0 && $produto_id > 0) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as offer_count, MAX(valor) as last_offer_value, MAX(quantidade) as last_quantity
        FROM ofertas 
        WHERE remetente_id = ? AND destinatario_id = ? AND produto_id = ? AND status = 'pendente' AND tipo = 'produto'
    ");
    $stmt->bind_param("iii", $user_id, $destinatario_id, $produto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $offer_check = $result->fetch_assoc();
    $stmt->close();
    
    if ($offer_check['offer_count'] > 0) {
        $can_make_offer = false;
        $valor_total = $offer_check['last_offer_value'] * $offer_check['last_quantity'];
        $existing_offer_message = "Você já tem uma oferta pendente de {$offer_check['last_quantity']}x €" . number_format($offer_check['last_offer_value'], 2) . " (Total: €" . number_format($valor_total, 2) . ") para este produto.";
    }
}

// Buscar ofertas pendentes para este usuário (como vendedor)
$stmt = $conn->prepare("
    SELECT o.*, u.utilizador as remetente_nome, p.nome as produto_nome, p.quantidade as produto_quantidade
    FROM ofertas o
    JOIN utilizadores u ON o.remetente_id = u.id_utilizadores
    JOIN produtos p ON o.produto_id = p.id
    WHERE o.destinatario_id = ? AND o.status = 'pendente' AND o.tipo = 'produto'
    ORDER BY o.data_criacao DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_offers = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Mensagens de Produtos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />
     <link rel="icon" type="image/png" href="../berto.png" />
    <style>
        :root {
            --primary-color: #f59e0b;
            --primary-hover: #d97706;
            --primary-light: #fbbf24;
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
            --shadow-glow: 0 0 20px rgba(245, 158, 11, 0.3);
            --transition-speed: 0.3s;
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-orange: #f59e0b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --service-color: #059669;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fef3c7 0%, #fef7cd 50%, #fefbf0 100%);
            color: var(--text-primary);
            line-height: 1.6;
            font-weight: 400;
            min-height: 100vh;
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
            color: var(--service-color);
            transition: color var(--transition-speed);
        }

        .navbar h1:hover {
            color: #047857;
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
            color: var(--service-color);
            background-color: rgba(5, 150, 105, 0.1);
        }

        .navbar-list a.active {
            color: var(--primary-color);
            background-color: rgba(245, 158, 11, 0.1);
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
            color: var(--service-color);
        }

        .messages-dropdown-list li a.services {
            border-left: 3px solid var(--service-color);
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
            background-color: rgba(245, 158, 11, 0.05);
            color: var(--primary-color);
        }

        .profile-dropdown-list hr {
            margin: 0.5rem 0;
            border: none;
            border-top: 1px solid var(--border-color);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .page-header h1 {
            font-size: 3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: var(--success-color);
            color: #065f46;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: var(--error-color);
            color: #991b1b;
        }

        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            border-color: var(--warning-color);
            color: #92400e;
        }

        /* Messages Layout */
        .messages-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            height: calc(100vh - 300px);
            min-height: 600px;
        }

        /* Conversations Panel */
        .conversations-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-lg);
            border: 1px solid rgba(245, 158, 11, 0.2);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .conversations-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(245, 158, 11, 0.2);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .conversations-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(245, 158, 11, 0.1);
            cursor: pointer;
            transition: all var(--transition-speed);
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            backdrop-filter: blur(10px);
        }

        .conversation-item:hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(251, 191, 36, 0.05));
            text-decoration: none;
            color: inherit;
            transform: translateX(5px);
        }

        .conversation-item.active {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1));
            border-right: 3px solid var(--primary-color);
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .conversation-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .product-badge {
            background: rgba(245, 158, 11, 0.1);
            color: var(--primary-color);
            padding: 0.125rem 0.5rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .product-badge.general {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-secondary);
        }

        .conversation-product {
            font-size: 0.75rem;
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .conversation-product.general {
            color: var(--text-secondary);
        }

        .conversation-preview {
            font-size: 0.875rem;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .conversation-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Chat Panel */
        .chat-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-lg);
            border: 1px solid rgba(245, 158, 11, 0.2);
            box-shadow: var(--shadow-medium);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(245, 158, 11, 0.2);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .chat-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chat-product-info {
            font-size: 0.875rem;
            color: var(--primary-color);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chat-product-info.general {
            color: var(--text-secondary);
        }

        .stock-info {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .messages-area {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background: linear-gradient(135deg, rgba(254, 243, 199, 0.3), rgba(254, 247, 205, 0.3));
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            animation: fadeIn 0.3s ease-out;
        }

        .message.own {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
            box-shadow: var(--shadow-soft);
        }

        .message.own .message-avatar {
            background: linear-gradient(135deg, var(--accent-blue), #60a5fa);
        }

        .message-content {
            max-width: 70%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 0.875rem 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(245, 158, 11, 0.1);
            position: relative;
        }

        .message.own .message-content {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .message-text {
            margin-bottom: 0.25rem;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .message-form {
            padding: 1.5rem;
            border-top: 1px solid rgba(245, 158, 11, 0.2);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .message-input-group {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            padding: 0.875rem 1rem;
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: var(--border-radius);
            resize: none;
            min-height: 44px;
            max-height: 120px;
            font-family: inherit;
            transition: all var(--transition-speed);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .message-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-glow);
        }

        .send-btn {
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-soft);
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .offer-section {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(239, 246, 255, 0.8);
            border: 1px solid rgba(191, 219, 254, 0.5);
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
        }

        .offer-section.disabled {
            background: rgba(243, 244, 246, 0.8);
            border: 1px solid rgba(209, 213, 219, 0.5);
            opacity: 0.7;
        }

        .offer-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 0.75rem;
            align-items: center;
        }

        .offer-input {
            padding: 0.75rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            font-size: 0.875rem;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .offer-input:disabled {
            background: rgba(249, 250, 251, 0.8);
            color: #6b7280;
            cursor: not-allowed;
        }

        .offer-btn {
            padding: 0.75rem 1.5rem;
            background: var(--accent-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-soft);
        }

        .offer-btn:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .offer-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .existing-offer-notice {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pending-offers {
            margin-bottom: 2rem;
        }

        .offer-card {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .offer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .offer-amount {
            font-size: 1.25rem;
            font-weight: 700;
            color: #92400e;
        }

        .offer-from {
            font-size: 0.875rem;
            color: #92400e;
        }

        .offer-details {
            font-size: 0.875rem;
            color: #92400e;
            margin-bottom: 1rem;
        }

        .offer-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .accept-btn {
            padding: 0.5rem 1rem;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .accept-btn:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .reject-btn {
            padding: 0.5rem 1rem;
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .reject-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .quantity-input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 6px;
            font-size: 0.875rem;
            text-align: center;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            text-align: center;
            padding: 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: rgba(245, 158, 11, 0.3);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-state p {
            margin-bottom: 1rem;
        }

        .empty-state small {
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar-container {
                padding: 1rem;
            }

            .navbar-list {
                display: none;
            }

            .main-container {
                padding: 2rem 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .messages-layout {
                grid-template-columns: 1fr;
                height: auto;
            }

            .conversations-panel {
                order: 2;
                max-height: 300px;
            }

            .chat-panel {
                order: 1;
                min-height: 500px;
            }

            .offer-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .navbar h1 {
                font-size: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .main-container {
                padding: 1rem;
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
                <li><a href="sobre.php">Sobre</a></li>
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

    <!-- Main Content -->
    <main class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Centro de Mensagens - Produtos</h1>
            <p>Gerencie suas conversas sobre produtos de forma organizada e profissional</p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Pending Offers -->
        <?php if ($pending_offers->num_rows > 0): ?>
            <div class="pending-offers">
                <h4 style="margin-bottom: 1rem; color: var(--text-primary);">
                    <i class="fas fa-clock"></i> Ofertas Pendentes para Seus Produtos
                </h4>
                <?php while ($offer = $pending_offers->fetch_assoc()): ?>
                    <div class="offer-card">
                        <div class="offer-header">
                            <div class="offer-amount">
                                <?= $offer['quantidade'] ?>x €<?= number_format($offer['valor'], 2) ?>
                                <span style="font-size: 0.875rem; font-weight: normal;">
                                    (Total: €<?= number_format($offer['valor'] * $offer['quantidade'], 2) ?>)
                                </span>
                            </div>
                            <div class="offer-from">de <?= htmlspecialchars($offer['remetente_nome']) ?></div>
                        </div>
                        <div class="offer-details">
                            <strong>Produto:</strong> <?= htmlspecialchars($offer['produto_nome']) ?><br>
                            <strong>Estoque disponível:</strong> <?= $offer['produto_quantidade'] ?> unidades
                        </div>
                        <form method="POST" action="product_offer_handler.php" style="display: inline;">
                            <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                            <input type="hidden" name="destinatario_id" value="<?= $destinatario_id ?>">
                            <input type="hidden" name="produto_id" value="<?= $offer['produto_id'] ?>">
                            <input type="hidden" name="quantidade" value="<?= $offer['quantidade'] ?>">
                            <div class="offer-actions">
                                <button type="submit" name="respond_offer" value="accept" class="accept-btn"
                                        onclick="return confirm('Aceitar esta oferta de <?= $offer['quantidade'] ?>x €<?= number_format($offer['valor'], 2) ?>?')"
                                        <?= $offer['produto_quantidade'] < $offer['quantidade'] ? 'disabled title="Estoque insuficiente"' : '' ?>>
                                    <i class="fas fa-check"></i> Aceitar
                                </button>
                                <button type="submit" name="respond_offer" value="reject" class="reject-btn"
                                        onclick="return confirm('Rejeitar esta oferta?')">
                                    <i class="fas fa-times"></i> Rejeitar
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <!-- Messages Layout -->
        <div class="messages-layout">
            <!-- Conversations Panel -->
            <div class="conversations-panel">
                <div class="conversations-header">
                    <h2><i class="fas fa-box"></i> Conversas sobre Produtos</h2>
                </div>
                <div class="conversations-list">
                    <?php if ($conversations->num_rows > 0): ?>
                        <?php while ($conversation = $conversations->fetch_assoc()): ?>
                            <?php 
                            $is_active = ($conversation['contact_id'] == $destinatario_id && 
                                         (($conversation['produto_id'] == $produto_id) || 
                                          ($conversation['produto_id'] === null && $produto_id === null)));
                            $url_params = "destinatario_id=" . $conversation['contact_id'];
                            if ($conversation['produto_id']) {
                                $url_params .= "&produto_id=" . $conversation['produto_id'];
                            }
                            ?>
                            <a href="?<?= $url_params ?>" 
                               class="conversation-item <?= $is_active ? 'active' : '' ?>">
                                <div class="conversation-header">
                                    <div class="conversation-name"><?= htmlspecialchars($conversation['contact_name']) ?></div>
                                    <div class="product-badge <?= $conversation['produto_id'] ? '' : 'general' ?>">
                                        <?= $conversation['produto_id'] ? 'Produto' : 'Geral' ?>
                                    </div>
                                </div>
                                <?php if ($conversation['product_name']): ?>
                                    <div class="conversation-product">
                                        <i class="fas fa-box"></i> <?= htmlspecialchars($conversation['product_name']) ?>
                                        <span style="color: #059669; font-weight: 600;">(<?= $conversation['product_quantity'] ?> em estoque)</span>
                                    </div>
                                <?php else: ?>
                                    <div class="conversation-product general">
                                        <i class="fas fa-comment"></i> Conversa geral
                                    </div>
                                <?php endif; ?>
                                <div class="conversation-preview"><?= htmlspecialchars(substr($conversation['last_message'], 0, 50)) ?>...</div>
                                <div class="conversation-time"><?= date('d/m H:i', strtotime($conversation['last_message_time'])) ?></div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhuma conversa ainda</p>
                            <small>Suas conversas sobre produtos aparecerão aqui</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Panel -->
            <div class="chat-panel">
                <?php if ($destinatario_id > 0): ?>
                    <div class="chat-header">
                        <h3><i class="fas fa-user"></i> <?= htmlspecialchars($contact_name) ?></h3>
                        <?php if ($product_name): ?>
                            <div class="chat-product-info">
                                <i class="fas fa-box"></i>
                                <span>Sobre: <?= htmlspecialchars($product_name) ?></span>
                                <span class="stock-info"><?= $product_quantity ?> em estoque</span>
                            </div>
                        <?php else: ?>
                            <div class="chat-product-info general">
                                <i class="fas fa-comment"></i>
                                <span>Conversa geral sobre produtos</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="messages-area" id="messagesArea">
                        <?php if ($messages->num_rows > 0): ?>
                            <?php while ($message = $messages->fetch_assoc()): ?>
                                <div class="message <?= $message['remetente_id'] == $user_id ? 'own' : '' ?>">
                                    <div class="message-avatar">
                                        <?= strtoupper(substr($message['remetente_nome'], 0, 1)) ?>
                                    </div>
                                    <div class="message-content">
                                        <div class="message-text"><?= htmlspecialchars($message['mensagem']) ?></div>
                                        <div class="message-time"><?= date('d/m H:i', strtotime($message['data_envio'])) ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-comment"></i>
                                <p>Nenhuma mensagem ainda</p>
                                <small>Inicie a conversa enviando uma mensagem</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="message-form">
                        <form method="POST" onsubmit="return validateMessage(this)">
                            <input type="hidden" name="destinatario_id" value="<?= $destinatario_id ?>">
                            <?php if ($produto_id): ?>
                                <input type="hidden" name="produto_id" value="<?= $produto_id ?>">
                            <?php endif; ?>
                            
                            <div class="message-input-group">
                                <textarea name="mensagem" class="message-input" placeholder="Digite sua mensagem..." required></textarea>
                                <button type="submit" name="send_message" class="send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                    Enviar
                                </button>
                            </div>
                        </form>

                        
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box"></i>
                        <h3>Selecione uma conversa</h3>
                        <p>Escolha uma conversa da lista ao lado para começar a conversar sobre produtos</p>
                        <small>
                            <i class="fas fa-lightbulb"></i> 
                            Cada produto tem sua própria conversa separada para melhor organização
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

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

        function validateMessage(form) {
            const message = form.mensagem.value.trim();
            if (message.length === 0) {
                showNotification('Por favor, digite uma mensagem', 'error');
                return false;
            }
            return true;
        }

        function validateOffer(form) {
            const valor = parseFloat(form.valor.value);
            const quantidade = parseInt(form.quantidade.value);
            const maxQuantity = <?= $product_quantity ?>;
            
            if (valor <= 0) {
                showNotification('O valor deve ser maior que zero', 'error');
                return false;
            }
            
            if (quantidade <= 0) {
                showNotification('A quantidade deve ser maior que zero', 'error');
                return false;
            }
            
            if (quantidade > maxQuantity) {
                showNotification(`Quantidade máxima disponível: ${maxQuantity}`, 'error');
                return false;
            }
            
            // Check if offer is disabled
            if (form.valor.disabled) {
                showNotification('Você já tem uma oferta pendente para este produto', 'warning');
                return false;
            }
            
            const total = valor * quantidade;
            return confirm(`Confirma o envio da oferta de ${quantidade}x €${valor.toFixed(2)} (Total: €${total.toFixed(2)})?`);
        }

        // Auto-scroll to bottom of messages
        const messagesArea = document.getElementById('messagesArea');
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // Auto-resize textarea
        document.querySelector('.message-input').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Refresh messages every 30 seconds
        setInterval(function() {
            if (window.location.search.includes('destinatario_id=')) {
                location.reload();
            }
        }, 30000);

        // Show notification about separate chats
        setTimeout(() => {
            if (document.querySelector('.conversation-item')) {
                showNotification('💡 Cada produto tem sua conversa separada para melhor organização!', 'info');
            }
        }, 2000);

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: ${type === 'success' ? 'var(--primary-color)' : type === 'error' ? 'var(--error-color)' : type === 'warning' ? 'var(--warning-color)' : 'var(--accent-blue)'};
                color: white;
                border-radius: 12px;
                box-shadow: var(--shadow-strong);
                z-index: 10000;
                font-weight: 500;
                animation: slideIn 0.3s ease-out;
                max-width: 400px;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            `;
            
            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            notification.innerHTML = `<i class="fas ${icon}"></i>${message}`;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Add CSS for notification animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>