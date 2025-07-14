<?php
session_start();

if (!isset($_SESSION['id_utilizadores'])) {
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

// Processar criação de disputa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_dispute'])) {
    try {
        $escrow_id = (int)$_POST['escrow_id'];
        $dispute_type = $_POST['dispute_type'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $evidence = [];
        
        // Processar evidências (arquivos, links, etc.)
        if (!empty($_POST['evidence_links'])) {
            $evidence['links'] = explode("\n", trim($_POST['evidence_links']));
        }
        
        // Verificar se o usuário pode criar disputa para este escrow
        $stmt = $conn->prepare("
            SELECT client_id, provider_id, total_amount, status 
            FROM escrow_transactions 
            WHERE id = ? AND (client_id = ? OR provider_id = ?)
        ");
        $stmt->bind_param("iii", $escrow_id, $user_id, $user_id);
        $stmt->execute();
        $escrow = $stmt->get_result()->fetch_assoc();
        
        if (!$escrow) {
            throw new Exception("Escrow não encontrado ou você não tem permissão para criar disputa");
        }
        
        // Verificar se já existe disputa ativa para este escrow
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM dispute_cases 
            WHERE escrow_id = ? AND status IN ('open', 'under_review')
        ");
        $stmt->bind_param("i", $escrow_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing['count'] > 0) {
            throw new Exception("Já existe uma disputa ativa para esta transação");
        }
        
        // Determinar respondente
        $respondent_id = ($escrow['client_id'] == $user_id) ? $escrow['provider_id'] : $escrow['client_id'];
        
        // Determinar prioridade baseada no valor
        $priority = 'medium';
        if ($escrow['total_amount'] > 1000) {
            $priority = 'high';
        } elseif ($escrow['total_amount'] > 5000) {
            $priority = 'urgent';
        } elseif ($escrow['total_amount'] < 100) {
            $priority = 'low';
        }
        
        // Criar disputa
        $dispute_id = $escrow_system->createDispute(
            $escrow_id, 
            $user_id, 
            $dispute_type, 
            $title, 
            $description, 
            $evidence
        );
        
        // Atualizar status do escrow
        $stmt = $conn->prepare("UPDATE escrow_transactions SET status = 'disputed' WHERE id = ?");
        $stmt->bind_param("i", $escrow_id);
        $stmt->execute();
        
        // Notificar a outra parte
        $stmt = $conn->prepare("
            INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, data_envio, tipo) 
            VALUES (?, ?, ?, NOW(), 'sistema')
        ");
        $notification = "⚠️ Uma disputa foi aberta contra você. Título: {$title}. Acesse a seção de disputas para responder.";
        $stmt->bind_param("iis", $user_id, $respondent_id, $notification);
        $stmt->execute();
        
        $success_message = "Disputa criada com sucesso! ID da disputa: #{$dispute_id}";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Processar resposta à disputa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_dispute'])) {
    try {
        $dispute_id = (int)$_POST['dispute_id'];
        $response = trim($_POST['response']);
        
        // Verificar se o usuário pode responder a esta disputa
        $stmt = $conn->prepare("
            SELECT * FROM dispute_cases 
            WHERE id = ? AND respondent_id = ? AND status = 'open'
        ");
        $stmt->bind_param("ii", $dispute_id, $user_id);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        
        if (!$dispute) {
            throw new Exception("Disputa não encontrada ou você não pode responder");
        }
        
        // Adicionar resposta
        $stmt = $conn->prepare("
            UPDATE dispute_cases 
            SET description = CONCAT(description, '\n\n--- RESPOSTA DO RESPONDENTE ---\n', ?),
                status = 'under_review',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("si", $response, $dispute_id);
        $stmt->execute();
        
        // Notificar o reclamante
        $stmt = $conn->prepare("
            INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, data_envio, tipo) 
            VALUES (?, ?, ?, NOW(), 'sistema')
        ");
        $notification = "📝 Resposta recebida na disputa #{$dispute_id}. A disputa está agora sob análise da administração.";
        $stmt->bind_param("iis", $user_id, $dispute['complainant_id'], $notification);
        $stmt->execute();
        
        $success_message = "Resposta enviada com sucesso! A disputa está agora sob análise.";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Obter escrows do usuário que podem ter disputas
$stmt = $conn->prepare("
    SELECT e.*, 
           CASE WHEN e.client_id = ? THEN 'cliente' ELSE 'prestador' END as user_role,
           CASE WHEN e.client_id = ? THEN u2.utilizador ELSE u1.utilizador END as other_party,
           COUNT(dc.id) as dispute_count
    FROM escrow_transactions e
    JOIN utilizadores u1 ON e.client_id = u1.id_utilizadores
    JOIN utilizadores u2 ON e.provider_id = u2.id_utilizadores
    LEFT JOIN dispute_cases dc ON e.id = dc.escrow_id
    WHERE (e.client_id = ? OR e.provider_id = ?) 
    AND e.status IN ('pending', 'partial_released', 'disputed', 'completed')
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$escrows = $stmt->get_result();

// Obter disputas do usuário
$stmt = $conn->prepare("
    SELECT dc.*, 
           e.total_amount,
           CASE WHEN dc.complainant_id = ? THEN 'reclamante' ELSE 'respondente' END as user_role,
           CASE WHEN dc.complainant_id = ? THEN u2.utilizador ELSE u1.utilizador END as other_party
    FROM dispute_cases dc
    JOIN escrow_transactions e ON dc.escrow_id = e.id
    JOIN utilizadores u1 ON dc.complainant_id = u1.id_utilizadores
    JOIN utilizadores u2 ON dc.respondent_id = u2.id_utilizadores
    WHERE dc.complainant_id = ? OR dc.respondent_id = ?
    ORDER BY dc.created_at DESC
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$disputes = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Centro de Disputas</title>
   
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
            --shadow-soft: 0 2px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 4px 25px rgba(0, 0, 0, 0.12);
            --shadow-strong: 0 8px 40px rgba(0, 0, 0, 0.15);
            --transition-speed: 0.3s;
            --border-radius: 16px;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --dispute-color: #dc2626;
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

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-soft);
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

        .navbar-list a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            transition: all var(--transition-speed);
            position: relative;
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
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

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
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success-color);
            color: #065f46;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--error-color);
            color: #991b1b;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all var(--transition-speed);
        }

        .tab.active {
            color: var(--dispute-color);
            border-bottom-color: var(--dispute-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-card {
            background: var(--card-background);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all var(--transition-speed);
            background: var(--card-background);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--dispute-color);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--dispute-color), #ef4444);
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .escrow-item {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .escrow-item:hover {
            border-color: var(--dispute-color);
            background: #fef2f2;
        }

        .escrow-item.selected {
            border-color: var(--dispute-color);
            background: #fef2f2;
        }

        .dispute-item {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .dispute-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .dispute-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.125rem;
        }

        .dispute-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .dispute-status.open {
            background: #fee2e2;
            color: #991b1b;
        }

        .dispute-status.under_review {
            background: #fef3c7;
            color: #92400e;
        }

        .dispute-status.resolved {
            background: #d1fae5;
            color: #065f46;
        }

        .dispute-status.closed {
            background: #f3f4f6;
            color: #374151;
        }

        .dispute-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .dispute-description {
            color: var(--text-secondary);
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .dispute-actions {
            display: flex;
            gap: 0.5rem;
        }

        .respond-btn {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .respond-btn:hover {
            background: var(--primary-hover);
        }

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

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .tabs {
                flex-direction: column;
                gap: 0;
            }

            .tab {
                text-align: left;
                border-bottom: 1px solid var(--border-color);
                border-right: 2px solid transparent;
            }

            .tab.active {
                border-bottom-color: var(--border-color);
                border-right-color: var(--dispute-color);
            }

            .dispute-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .dispute-meta {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
        .footer {
            background: var(--text-primary);
            color: white;
            margin-top: 0;
        }

        .footer .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 4rem 2rem 2rem;
        }

        .footer .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-col h4 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--primary-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 0.75rem;
        }

        .footer-col ul li a {
            color: #d1d5db;
            text-decoration: none;
            transition: color var(--transition-speed);
            font-weight: 400;
        }

        .footer-col ul li a:hover {
            color: white;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: #d1d5db;
            transition: all var(--transition-speed);
        }

        .social-links a:hover {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #9ca3af;
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .highlight-card,
        .step,
        .category {
            animation: fadeIn 0.6s ease-out forwards;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
            }

            .navbar-list {
                display: none;
            }

            .hero {
                padding: 5rem 1rem;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.2rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .highlights .container,
            .steps,
            .categories {
                grid-template-columns: 1fr;
            }

            .how-it-works h2,
            .popular-categories h2,
            .cta h2 {
                font-size: 2rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .container {
                padding: 0 1rem;
            }
        }

    </style>
</head>

<body>
   <nav class="navbar">
    <h1>Berto</h1>
    <ul class="navbar-list">
        <li><a href="index.php">Início</a></li>
        <li><a href="produtos.php">Produtos</a></li>
        <li><a href="servicos_resultados.php">Serviços</a></li>
        <li><a href="suporte.php">Suporte</a></li>
        <li><a href="messages.php">Mensagens</a></li>
        <li><a href="disputes.php" class="active">Disputas</a></li>
    </ul>

    <div class="profile-dropdown">
        <div onclick="toggle()" class="profile-dropdown-btn">
            <div class="profile-img">
                <i class="fa-solid fa-user"></i>
            </div>
            <span>
                <?php echo htmlspecialchars($nome_usuario); ?>
                <i class="fa-solid fa-chevron-down"></i>
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
                    O Meu Saldo
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
</nav>

<main class="main-container">
    <div class="page-header">
        <h1>Centro de Disputas</h1>
        <p>Resolva conflitos e problemas com as suas transações</p>
    </div>

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

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="switchTab('create')">
            <i class="fas fa-plus"></i> Criar Disputa
        </button>
        <button class="tab" onclick="switchTab('my-disputes')">
            <i class="fas fa-gavel"></i> As Minhas Disputas (<?= $disputes->num_rows ?>)
        </button>
    </div>

    <!-- Tab: Criar Disputa -->
    <div id="create-tab" class="tab-content active">
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-exclamation-triangle"></i>
                Criar Nova Disputa
            </h2>

            <?php if ($escrows->num_rows > 0): ?>
                <form method="POST" onsubmit="return validateDisputeForm(this)">
                    <div class="form-group">
                        <label>Selecionar Transação</label>
                        <div id="escrow-list">
                            <?php while ($escrow = $escrows->fetch_assoc()): ?>
                                <div class="escrow-item" onclick="selectEscrow(<?= $escrow['id'] ?>, this)">
                                    <input type="radio" name="escrow_id" value="<?= $escrow['id'] ?>" style="display: none;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong>Transação #<?= $escrow['id'] ?></strong>
                                            <br>
                                            <small>
                                                <?= ucfirst($escrow['user_role']) ?> • 
                                                Outra parte: <?= htmlspecialchars($escrow['other_party']) ?> • 
                                                Estado: <?= ucfirst($escrow['status']) ?>
                                            </small>
                                        </div>
                                        <div>
                                            <strong>€<?= number_format($escrow['total_amount'], 2) ?></strong>
                                            <?php if ($escrow['dispute_count'] > 0): ?>
                                                <br><small style="color: var(--dispute-color);">
                                                    <?= $escrow['dispute_count'] ?> disputa(s) existente(s)
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="dispute_type">Tipo de Problema</label>
                        <select name="dispute_type" id="dispute_type" required>
                            <option value="">Selecione o tipo...</option>
                            <option value="non_delivery">Não entregue</option>
                            <option value="poor_quality">Qualidade baixa</option>
                            <option value="payment_issue">Problema de pagamento</option>
                            <option value="contract_breach">Quebra de contrato</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Título da Disputa</label>
                        <input type="text" name="title" id="title" placeholder="Descreva brevemente o problema" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Descrição Detalhada</label>
                        <textarea name="description" id="description" placeholder="Explique detalhadamente o que aconteceu, quando ocorreu, e qual a solução que espera..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="evidence_links">Evidências (Links)</label>
                        <textarea name="evidence_links" id="evidence_links" placeholder="Cole aqui links para fotos, documentos, conversas, etc. (um por linha)"></textarea>
                    </div>

                    <button type="submit" name="create_dispute" class="submit-btn">
                        <i class="fas fa-gavel"></i>
                        Criar Disputa
                    </button>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-handshake"></i>
                    <p>Nenhuma transação disponível para disputa</p>
                    <small>Precisa de ter transações ativas para criar disputas</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Minhas Disputas -->
    <div id="my-disputes-tab" class="tab-content">
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-gavel"></i>
                As Minhas Disputas
            </h2>

            <?php if ($disputes->num_rows > 0): ?>
                <?php while ($dispute = $disputes->fetch_assoc()): ?>
                    <div class="dispute-item">
                        <div class="dispute-header">
                            <div class="dispute-title">
                                #<?= $dispute['id'] ?> - <?= htmlspecialchars($dispute['title']) ?>
                            </div>
                            <div class="dispute-status <?= $dispute['status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $dispute['status'])) ?>
                            </div>
                        </div>
                        
                        <div class="dispute-meta">
                            <span><strong>Tipo:</strong> <?= ucfirst(str_replace('_', ' ', $dispute['dispute_type'])) ?></span>
                            <span><strong>Valor:</strong> €<?= number_format($dispute['total_amount'], 2) ?></span>
                            <span><strong>Papel:</strong> <?= ucfirst($dispute['user_role']) ?></span>
                            <span><strong>Outra parte:</strong> <?= htmlspecialchars($dispute['other_party']) ?></span>
                            <span><strong>Criado:</strong> <?= date('d/m/Y H:i', strtotime($dispute['created_at'])) ?></span>
                        </div>
                        
                        <div class="dispute-description">
                            <?= nl2br(htmlspecialchars($dispute['description'])) ?>
                        </div>
                        
                        <?php if ($dispute['status'] == 'open' && $dispute['user_role'] == 'respondente'): ?>
                            <div class="dispute-actions">
                                <button class="respond-btn" onclick="showResponseForm(<?= $dispute['id'] ?>)">
                                    <i class="fas fa-reply"></i> Responder
                                </button>
                            </div>
                            
                            <div id="response-form-<?= $dispute['id'] ?>" style="display: none; margin-top: 1rem;">
                                <form method="POST">
                                    <input type="hidden" name="dispute_id" value="<?= $dispute['id'] ?>">
                                    <div class="form-group">
                                        <label>A sua Resposta</label>
                                        <textarea name="response" placeholder="Explique a sua versão dos factos..." required></textarea>
                                    </div>
                                    <button type="submit" name="respond_dispute" class="submit-btn">
                                        <i class="fas fa-paper-plane"></i>
                                        Enviar Resposta
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($dispute['resolution']): ?>
                            <div style="margin-top: 1rem; padding: 1rem; background: #f0f9ff; border-radius: 8px;">
                                <strong>Resolução:</strong><br>
                                <?= nl2br(htmlspecialchars($dispute['resolution'])) ?>
                                <?php if ($dispute['resolution_amount']): ?>
                                    <br><strong>Valor da resolução:</strong> €<?= number_format($dispute['resolution_amount'], 2) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-peace"></i>
                    <p>Nenhuma disputa encontrada</p>
                    <small>As suas disputas aparecerão aqui</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="footer-col">
                <h4>Empresa</h4>
                <ul>
                    <li><a href="#">Sobre Nós</a></li>
                    <li><a href="#">Berto © 2025 by Afonso Nunes Ferraz está licenciado sob CC BY-NC-SA 4.0</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Ajuda</h4>
                <ul>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Como Funciona</a></li>
                    <li><a href="suporte.php">Suporte</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Siga-nos</h4>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Berto. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>
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

        // Tab switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function selectEscrow(escrowId, element) {
            // Remove selection from all items
            document.querySelectorAll('.escrow-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selection to clicked item
            element.classList.add('selected');
            
            // Check the radio button
            element.querySelector('input[type="radio"]').checked = true;
        }

        function showResponseForm(disputeId) {
            const form = document.getElementById(`response-form-${disputeId}`);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function validateDisputeForm(form) {
            const escrowId = form.escrow_id.value;
            const disputeType = form.dispute_type.value;
            const title = form.title.value.trim();
            const description = form.description.value.trim();
            
            if (!escrowId) {
                alert('Por favor, selecione uma transação');
                return false;
            }
            
            if (!disputeType) {
                alert('Por favor, selecione o tipo de problema');
                return false;
            }
            
            if (!title || title.length < 10) {
                alert('Por favor, insira um título mais descritivo (mínimo 10 caracteres)');
                return false;
            }
            
            if (!description || description.length < 50) {
                alert('Por favor, forneça uma descrição mais detalhada (mínimo 50 caracteres)');
                return false;
            }
            
            return confirm('Tem certeza que deseja criar esta disputa? Esta ação não pode ser desfeita.');
        }
    </script>
</body>
</html>