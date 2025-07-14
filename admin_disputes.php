<?php
session_start();

// Verificar se é administrador
if (!isset($_SESSION['id_utilizadores']) || $_SESSION['id_tipos_utilizador'] != 0) {
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

$admin_id = $_SESSION['id_utilizadores'];
$nome_admin = $_SESSION['utilizador'];

// Processar resolução de disputa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_dispute'])) {
    try {
        $dispute_id = (int) $_POST['dispute_id'];
        $resolution = trim($_POST['resolution']);
        $resolution_amount = isset($_POST['resolution_amount']) ? (float) $_POST['resolution_amount'] : null;
        $winner = $_POST['winner']; // 'complainant', 'respondent', 'partial'

        $conn->begin_transaction();

        // Buscar dados da disputa
        $stmt = $conn->prepare("
            SELECT dc.*, e.total_amount, e.amount_released, e.client_id, e.provider_id
            FROM dispute_cases dc
            JOIN escrow_transactions e ON dc.escrow_id = e.id
            WHERE dc.id = ?
        ");
        $stmt->bind_param("i", $dispute_id);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();

        if (!$dispute) {
            throw new Exception("Disputa não encontrada");
        }

        // Atualizar disputa
        $stmt = $conn->prepare("
            UPDATE dispute_cases 
            SET status = 'resolved', 
                resolution = ?, 
                resolution_amount = ?,
                assigned_admin = ?,
                resolution_date = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sdii", $resolution, $resolution_amount, $admin_id, $dispute_id);
        $stmt->execute();

        // Processar reembolso/liberação baseado na decisão
        if ($winner === 'complainant') {
            // Reembolso total para o reclamante
            $refund_amount = $dispute['total_amount'] - $dispute['amount_released'];
            if ($refund_amount > 0) {
                // Adicionar ao saldo do cliente
                $stmt = $conn->prepare("
                    INSERT INTO user_balance_transactions 
                    (user_id, amount, transaction_type, reference_id, description, status) 
                    VALUES (?, ?, 'refund', ?, ?, 'completed')
                ");
                $description = "Reembolso por disputa #$dispute_id - Decisão administrativa";
                $stmt->bind_param("idis", $dispute['client_id'], $refund_amount, $dispute_id, $description);
                $stmt->execute();

                // Atualizar saldo
                $stmt = $conn->prepare("
                    INSERT INTO user_balances (user_id, total_balance, available_balance) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        total_balance = total_balance + ?, 
                        available_balance = available_balance + ?
                ");
                $stmt->bind_param("idddd", $dispute['client_id'], $refund_amount, $refund_amount, $refund_amount, $refund_amount);
                $stmt->execute();
            }

            // Atualizar escrow
            $stmt = $conn->prepare("UPDATE escrow_transactions SET status = 'refunded' WHERE id = ?");
            $stmt->bind_param("i", $dispute['escrow_id']);
            $stmt->execute();

        } elseif ($winner === 'respondent') {
            // Liberar pagamento total para o prestador
            $remaining_amount = $dispute['total_amount'] - $dispute['amount_released'];
            if ($remaining_amount > 0) {
                // Adicionar ao saldo do prestador
                $stmt = $conn->prepare("
                    INSERT INTO user_balance_transactions 
                    (user_id, amount, transaction_type, reference_id, description, status) 
                    VALUES (?, ?, 'service_payment', ?, ?, 'completed')
                ");
                $description = "Liberação por disputa #$dispute_id - Decisão administrativa";
                $stmt->bind_param("idis", $dispute['provider_id'], $remaining_amount, $dispute_id, $description);
                $stmt->execute();

                // Atualizar saldo
                $stmt = $conn->prepare("
                    INSERT INTO user_balances (user_id, total_balance, available_balance) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        total_balance = total_balance + ?, 
                        available_balance = available_balance + ?
                ");
                $stmt->bind_param("idddd", $dispute['provider_id'], $remaining_amount, $remaining_amount, $remaining_amount, $remaining_amount);
                $stmt->execute();
            }

            // Atualizar escrow
            $stmt = $conn->prepare("UPDATE escrow_transactions SET status = 'completed', amount_released = total_amount WHERE id = ?");
            $stmt->bind_param("i", $dispute['escrow_id']);
            $stmt->execute();

        } elseif ($winner === 'partial' && $resolution_amount) {
            // Divisão personalizada
            $client_amount = $resolution_amount;
            $provider_amount = ($dispute['total_amount'] - $dispute['amount_released']) - $client_amount;

            if ($client_amount > 0) {
                // Reembolso parcial para cliente
                $stmt = $conn->prepare("
                    INSERT INTO user_balance_transactions 
                    (user_id, amount, transaction_type, reference_id, description, status) 
                    VALUES (?, ?, 'refund', ?, ?, 'completed')
                ");
                $description = "Reembolso parcial por disputa #$dispute_id";
                $stmt->bind_param("idis", $dispute['client_id'], $client_amount, $dispute_id, $description);
                $stmt->execute();

                $stmt = $conn->prepare("
                    INSERT INTO user_balances (user_id, total_balance, available_balance) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        total_balance = total_balance + ?, 
                        available_balance = available_balance + ?
                ");
                $stmt->bind_param("idddd", $dispute['client_id'], $client_amount, $client_amount, $client_amount, $client_amount);
                $stmt->execute();
            }

            if ($provider_amount > 0) {
                // Pagamento parcial para prestador
                $stmt = $conn->prepare("
                    INSERT INTO user_balance_transactions 
                    (user_id, amount, transaction_type, reference_id, description, status) 
                    VALUES (?, ?, 'service_payment', ?, ?, 'completed')
                ");
                $description = "Pagamento parcial por disputa #$dispute_id";
                $stmt->bind_param("idis", $dispute['provider_id'], $provider_amount, $dispute_id, $description);
                $stmt->execute();

                $stmt = $conn->prepare("
                    INSERT INTO user_balances (user_id, total_balance, available_balance) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        total_balance = total_balance + ?, 
                        available_balance = available_balance + ?
                ");
                $stmt->bind_param("idddd", $dispute['provider_id'], $provider_amount, $provider_amount, $provider_amount, $provider_amount);
                $stmt->execute();
            }

            // Atualizar escrow
            $stmt = $conn->prepare("UPDATE escrow_transactions SET status = 'completed' WHERE id = ?");
            $stmt->bind_param("i", $dispute['escrow_id']);
            $stmt->execute();
        }

        // Notificar as partes
        $stmt = $conn->prepare("
            INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, data_envio, tipo) 
            VALUES (?, ?, ?, NOW(), 'sistema')
        ");

        $notification = "⚖️ Disputa #{$dispute_id} foi resolvida pela administração. Resolução: " . substr($resolution, 0, 100) . "...";

        // Notificar reclamante
        $stmt->bind_param("iis", $admin_id, $dispute['complainant_id'], $notification);
        $stmt->execute();

        // Notificar respondente
        $stmt->bind_param("iis", $admin_id, $dispute['respondent_id'], $notification);
        $stmt->execute();

        $conn->commit();
        $success_message = "Disputa resolvida com sucesso!";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Obter disputas pendentes
$stmt = $conn->prepare("
    SELECT dc.*, 
           e.total_amount, e.amount_released,
           u1.utilizador as complainant_name,
           u2.utilizador as respondent_name,
           u3.utilizador as admin_name
    FROM dispute_cases dc
    JOIN escrow_transactions e ON dc.escrow_id = e.id
    JOIN utilizadores u1 ON dc.complainant_id = u1.id_utilizadores
    JOIN utilizadores u2 ON dc.respondent_id = u2.id_utilizadores
    LEFT JOIN utilizadores u3 ON dc.assigned_admin = u3.id_utilizadores
    ORDER BY 
        CASE dc.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        dc.created_at ASC
");
$stmt->execute();
$disputes = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Administração de Disputas</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />
    <style>
        :root {
            --primary-color: #059669;
            --admin-color: #7c3aed;
            --admin-hover: #6d28d9;
            --dispute-color: #dc2626;
            --background-color: #fafafa;
            --card-background: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --shadow-soft: 0 2px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 4px 25px rgba(0, 0, 0, 0.12);
            --transition-speed: 0.3s;
            --border-radius: 16px;
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
        }

        .admin-header {
            background: linear-gradient(135deg, var(--admin-color), var(--admin-hover));
            color: white;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .admin-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-background);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin-color);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .dispute-card {
            background: var(--card-background);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .dispute-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
        }

        .dispute-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-urgent {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-high {
            background: #fef3c7;
            color: #92400e;
        }

        .priority-medium {
            background: #dbeafe;
            color: #1e40af;
        }

        .priority-low {
            background: #f3f4f6;
            color: #374151;
        }

        .dispute-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }

        .meta-item {
            font-size: 0.875rem;
        }

        .meta-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .meta-value {
            color: var(--text-secondary);
        }

        .dispute-description {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .resolution-form {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
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
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .resolve-btn {
            background: var(--admin-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .resolve-btn:hover {
            background: var(--admin-hover);
            transform: translateY(-1px);
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

        @media (max-width: 768px) {
            .main-container {
                padding: 0 1rem 1rem;
            }

            .dispute-header {
                flex-direction: column;
                gap: 1rem;
            }

            .dispute-meta {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
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

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

<nav class="navbar">
        <h1>Berto</h1>
        <ul class="navbar-list">
            <li><a href="index.php">Início</a></li>
            <li><a href="produtos.php">Produtos</a></li>
            <li><a href="serviços_login.php">Serviços</a></li>
            <li><a href="suporte.php">Suporte</a></li>
            <li><a href="messages.php">Mensagens</a></li>
            <li><a href="#">Sobre</a></li>
        </ul>
        <div class="profile-dropdown">
            <div onclick="toggle()" class="profile-dropdown-btn">
                <div class="profile-img">
                    <i class="fa-solid fa-user"></i>
                </div>
                <span>
                    <?php echo htmlspecialchars($nome_admin); ?>
                    <i class="fa-solid fa-chevron-down" style="margin-left: 0.5rem; font-size: 0.75rem;"></i>
                </span>
            </div>
            <ul class="profile-dropdown-list">
                <?php if (isset($_SESSION['utilizador'])): ?>
                    <li class="profile-dropdown-list-item">
                        <a href="utilizador/profile/index.php">
                            <i class="fa-regular fa-user"></i>
                            Editar Perfil
                        </a>
                    </li>
                    <li class="profile-dropdown-list-item">
                        <a href="#">
                            <i class="fa-solid fa-sliders"></i>
                            Definições
                        </a>
                    </li>
                    <li class="profile-dropdown-list-item">
                        <a href="utilizador/gestao_produtos.php">
                            <i class="fa-solid fa-box"></i>
                            Gestão de produtos
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
                <?php else: ?>
                    <li class="profile-dropdown-list-item">
                        <a href="logintexte.php">
                            <i class="fa-solid fa-sign-in-alt"></i>
                            Iniciar Sessão
                        </a>
                    </li>
                    <li class="profile-dropdown-list-item">
                        <a href="registop2.php">
                            <i class="fa-solid fa-user-plus"></i>
                            Registar
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="admin-header">
        <h1><i class="fas fa-gavel"></i> Administração de Disputas</h1>
        <p>Painel administrativo para resolução de conflitos</p>
    </div>

    <main class="main-container">
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

        <!-- Estatísticas -->
        <div class="stats-grid">
            <?php
            $disputes->data_seek(0);
            $total = $disputes->num_rows;
            $open = $under_review = $resolved = $urgent = 0;

            while ($dispute = $disputes->fetch_assoc()) {
                switch ($dispute['status']) {
                    case 'open':
                        $open++;
                        break;
                    case 'under_review':
                        $under_review++;
                        break;
                    case 'resolved':
                        $resolved++;
                        break;
                }
                if ($dispute['priority'] === 'urgent')
                    $urgent++;
            }
            ?>

            <div class="stat-card">
                <div class="stat-number"><?= $total ?></div>
                <div class="stat-label">Total de Disputas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $open ?></div>
                <div class="stat-label">Abertas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $under_review ?></div>
                <div class="stat-label">Em Análise</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $urgent ?></div>
                <div class="stat-label">Urgentes</div>
            </div>
        </div>

        <!-- Lista de Disputas -->
        <?php $disputes->data_seek(0); ?>
        <?php while ($dispute = $disputes->fetch_assoc()): ?>
            <div class="dispute-card">
                <div class="dispute-header">
                    <div>
                        <div class="dispute-title">
                            Disputa #<?= $dispute['id'] ?> - <?= htmlspecialchars($dispute['title']) ?>
                        </div>
                        <div style="margin-top: 0.5rem;">
                            <span class="priority-badge priority-<?= $dispute['priority'] ?>">
                                <?= ucfirst($dispute['priority']) ?>
                            </span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 600; color: var(--admin-color);">
                            €<?= number_format($dispute['total_amount'], 2) ?>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            <?= date('d/m/Y H:i', strtotime($dispute['created_at'])) ?>
                        </div>
                    </div>
                </div>

                <div class="dispute-meta">
                    <div class="meta-item">
                        <div class="meta-label">Tipo:</div>
                        <div class="meta-value"><?= ucfirst(str_replace('_', ' ', $dispute['dispute_type'])) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Reclamante:</div>
                        <div class="meta-value"><?= htmlspecialchars($dispute['complainant_name']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Respondente:</div>
                        <div class="meta-value"><?= htmlspecialchars($dispute['respondent_name']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Estado:</div>
                        <div class="meta-value"><?= ucfirst(str_replace('_', ' ', $dispute['status'])) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">ID de Caução:</div>
                        <div class="meta-value">#<?= $dispute['escrow_id'] ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Valor Libertado:</div>
                        <div class="meta-value">€<?= number_format($dispute['amount_released'], 2) ?></div>
                    </div>
                </div>

                <div class="dispute-description">
                    <strong>Descrição:</strong><br>
                    <?= nl2br(htmlspecialchars($dispute['description'])) ?>
                </div>

                <?php if ($dispute['status'] !== 'resolved'): ?>
                    <div class="resolution-form">
                        <h4 style="margin-bottom: 1rem; color: var(--admin-color);">
                            <i class="fas fa-gavel"></i> Resolver Disputa
                        </h4>

                        <form method="POST">
                            <input type="hidden" name="dispute_id" value="<?= $dispute['id'] ?>">

                            <div class="form-group">
                                <label>Decisão</label>
                                <select name="winner" required onchange="toggleAmountField(this, <?= $dispute['id'] ?>)">
                                    <option value="">Selecione a decisão...</option>
                                    <option value="complainant">A favor do reclamante (reembolso total)</option>
                                    <option value="respondent">A favor do respondente (libertar pagamento)</option>
                                    <option value="partial">Divisão personalizada</option>
                                </select>
                            </div>

                            <div class="form-group" id="amount-field-<?= $dispute['id'] ?>" style="display: none;">
                                <label>Valor para o reclamante (€)</label>
                                <input type="number" name="resolution_amount" step="0.01" min="0"
                                    max="<?= $dispute['total_amount'] - $dispute['amount_released'] ?>">
                                <small>Máximo:
                                    €<?= number_format($dispute['total_amount'] - $dispute['amount_released'], 2) ?></small>
                            </div>

                            <div class="form-group">
                                <label>Resolução Detalhada</label>
                                <textarea name="resolution"
                                    placeholder="Explique a decisão tomada, justificações e próximos passos..."
                                    required></textarea>
                            </div>

                            <button type="submit" name="resolve_dispute" class="resolve-btn"
                                onclick="return confirm('Tem a certeza desta decisão? Esta ação não pode ser desfeita.')">
                                <i class="fas fa-gavel"></i> Resolver Disputa
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 1rem;">
                        <strong style="color: #1e40af;">Resolução:</strong><br>
                        <?= nl2br(htmlspecialchars($dispute['resolution'])) ?>
                        <?php if ($dispute['resolution_amount']): ?>
                            <br><strong>Valor da resolução:</strong> €<?= number_format($dispute['resolution_amount'], 2) ?>
                        <?php endif; ?>
                        <br><small>Resolvido por: <?= htmlspecialchars($dispute['admin_name'] ?: 'Sistema') ?> em
                            <?= date('d/m/Y H:i', strtotime($dispute['resolution_date'])) ?></small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>

        <?php if ($disputes->num_rows === 0): ?>
            <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                <i class="fas fa-peace" style="font-size: 3rem; margin-bottom: 1rem; color: #d1d5db;"></i>
                <p>Nenhuma disputa encontrada</p>
                <small>Todas as disputas aparecerão aqui</small>
            </div>
        <?php endif; ?>
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
        function toggleAmountField(select, disputeId) {
            const amountField = document.getElementById(`amount-field-${disputeId}`);
            if (select.value === 'partial') {
                amountField.style.display = 'block';
                amountField.querySelector('input').required = true;
            } else {
                amountField.style.display = 'none';
                amountField.querySelector('input').required = false;
            }
        }
    </script>
    <script>
function toggle() {
    const dropdown = document.querySelector('.profile-dropdown');
    dropdown.classList.toggle('active');
}

// Fecha o dropdown ao clicar fora
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.profile-dropdown');
    if (!dropdown.contains(event.target) && dropdown.classList.contains('active')) {
        dropdown.classList.remove('active');
    }
});
</script>
</body>

</html>