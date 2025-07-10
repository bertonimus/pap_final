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

// Processar submissão de prova
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_proof'])) {
    try {
        $escrow_id = (int)$_POST['escrow_id'];
        $proof_type = $_POST['proof_type'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $external_link = trim($_POST['external_link']) ?: null;
        
        // VERIFICAÇÃO CRÍTICA: Confirmar que o usuário é o prestador
        if (!$escrow_system->isUserProviderOfEscrow($escrow_id, $user_id)) {
            throw new Exception("❌ ACESSO NEGADO: Apenas o prestador pode submeter provas de entrega. Você não é o prestador deste serviço.");
        }
        
        // VERIFICAR FLUXO OBRIGATÓRIO DE PROVAS
        $current_proofs = $escrow_system->getEscrowProofs($escrow_id);
        $proof_count = $current_proofs->num_rows;
        $has_progress_update = false;
        $has_partial_delivery = false;
        
        // Verificar tipos de provas já submetidas
        while ($existing_proof = $current_proofs->fetch_assoc()) {
            if ($existing_proof['proof_type'] === 'progress_update') {
                $has_progress_update = true;
            }
            if ($existing_proof['proof_type'] === 'partial_delivery') {
                $has_partial_delivery = true;
            }
        }
        
        // REGRAS DO FLUXO OBRIGATÓRIO
        if ($proof_count === 0 && $proof_type !== 'progress_update') {
            throw new Exception("❌ PRIMEIRA PROVA: Deve submeter uma 'Atualização de Progresso' como primeira prova.");
        }
        
        if ($proof_count === 1 && !$has_progress_update) {
            throw new Exception("❌ FLUXO INCORRETO: A primeira prova deve ser uma 'Atualização de Progresso'.");
        }
        
        if ($proof_count === 1 && $has_progress_update && $proof_type !== 'partial_delivery') {
            throw new Exception("❌ SEGUNDA PROVA: Após a atualização de progresso, deve submeter uma 'Entrega Parcial'.");
        }
        
        if ($proof_count >= 2 && !$has_partial_delivery) {
            throw new Exception("❌ FLUXO INCORRETO: Deve ter uma 'Entrega Parcial' antes de outras provas.");
        }
        
        if ($proof_count >= 2 && $proof_type === 'partial_delivery') {
            throw new Exception("❌ ENTREGA PARCIAL JÁ SUBMETIDA: Só pode submeter uma entrega parcial. Use outros tipos de prova.");
        }
        
        $file_data = null;
        if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
            $file_data = $_FILES['proof_file'];
        }
        
        $proof_id = $escrow_system->submitDeliveryProof(
            $escrow_id, $user_id, $proof_type, $title, $description, $file_data, $external_link
        );
        
        $success_message = "✅ Prova de entrega submetida com sucesso! O cliente foi notificado e pode revisar sua entrega.";
        header("Location: ".$_SERVER['PHP_SELF']);
    exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Processar aprovação/rejeição de prova (apenas clientes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_proof'])) {
    try {
        $proof_id = (int)$_POST['proof_id'];
        $action = $_POST['review_proof']; // 'approve' ou 'reject'
        $feedback = trim($_POST['client_feedback']) ?: null;
        
        // Verificar se o usuário é o cliente desta prova
        $stmt = $conn->prepare("
            SELECT dp.*, e.client_id, e.total_amount, e.amount_released
            FROM delivery_proofs dp
            JOIN escrow_transactions e ON dp.escrow_id = e.id
            WHERE dp.id = ? AND e.client_id = ?
        ");
        $stmt->bind_param("ii", $proof_id, $user_id);
        $stmt->execute();
        $proof_result = $stmt->get_result();
        $proof = $proof_result->fetch_assoc();
        
        if (!$proof) {
            throw new Exception("❌ Prova não encontrada ou você não tem permissão para revisar.");
        }
        
        if ($action === 'approve') {
            // Aprovar prova
            $stmt = $conn->prepare("
                UPDATE delivery_proofs 
                SET status = 'approved', client_feedback = ?, reviewed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $feedback, $proof_id);
            $stmt->execute();
            
            // Se é entrega final, liberar pagamento restante e adicionar ao saldo
            if ($proof['proof_type'] === 'final_delivery') {
                $escrow_system->releaseFinalPayment($proof['escrow_id'], $user_id);
                $success_message = "✅ Prova aprovada e pagamento final liberado para o saldo do prestador!";
            } else {
                $success_message = "✅ Prova aprovada com sucesso!";
            }
            
        } elseif ($action === 'reject') {
            // Rejeitar prova
            $stmt = $conn->prepare("
                UPDATE delivery_proofs 
                SET status = 'rejected', client_feedback = ?, reviewed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $feedback, $proof_id);
            $stmt->execute();
            
            $success_message = "❌ Prova rejeitada. O prestador foi notificado.";
        }
        header("Location: ".$_SERVER['PHP_SELF']);
    exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Obter escrows onde o usuário é PRESTADOR
$escrows = $escrow_system->getProviderEscrows($user_id);

// Obter provas submetidas pelo prestador
$stmt = $conn->prepare("
    SELECT dp.*, e.total_amount, e.client_id,
           u.utilizador as client_name
    FROM delivery_proofs dp
    JOIN escrow_transactions e ON dp.escrow_id = e.id
    JOIN utilizadores u ON e.client_id = u.id_utilizadores

    ORDER BY dp.submitted_at DESC
");

$stmt->execute();
$proofs = $stmt->get_result();

// Obter provas para revisar (se o usuário é cliente)
$stmt = $conn->prepare("
    SELECT dp.*, e.total_amount, e.client_id,
           u.utilizador as provider_name
    FROM delivery_proofs dp
    JOIN escrow_transactions e ON dp.escrow_id = e.id
    JOIN utilizadores u ON e.provider_id = u.id_utilizadores
    WHERE e.client_id = ? AND dp.status = 'pending_review'
    ORDER BY dp.submitted_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$proofs_to_review = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Provas de Entrega</title>
    <link rel="stylesheet" href="styles/header2.css" />
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

        .page-header p {
            font-size: 1.125rem;
            color: var(--text-secondary);
        }

        .flow-explanation {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .flow-explanation h3 {
            color: #1E40AF;
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .flow-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .flow-step {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #3B82F6;
            text-align: center;
        }

        .flow-step .step-number {
            background: #3B82F6;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            margin: 0 auto 0.5rem;
        }

        .flow-step .step-title {
            font-weight: 600;
            color: #1E40AF;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .flow-step .step-desc {
            font-size: 0.75rem;
            color: #6B7280;
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
            background-color: rgba(16, 185, 129, 0.1);
            border-color: var(--success-color);
            color: #065f46;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: var(--error-color);
            color: #991b1b;
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
            color: var(--text-primary);
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
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 2rem;
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            background: #f9fafb;
            color: var(--text-secondary);
            transition: all var(--transition-speed);
        }

        .file-upload:hover .file-upload-label {
            border-color: var(--primary-color);
            background: rgba(5, 150, 105, 0.05);
        }

        .submit-btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .proof-item {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .proof-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }

        .proof-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .proof-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .proof-status.pending_review {
            background: #fef3c7;
            color: #92400e;
        }

        .proof-status.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .proof-status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .proof-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: rgba(5, 150, 105, 0.1);
            color: var(--primary-color);
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .review-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .approve-btn {
            padding: 0.5rem 1rem;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all var(--transition-speed);
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

        .feedback-textarea {
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            resize: vertical;
            min-height: 60px;
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
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .navbar-container {
                padding: 1rem;
            }

            .navbar-list {
                display: none;
            }

            .main-container {
                padding: 1rem;
            }
            
            .section-card {
                padding: 1.5rem;
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
                border-right-color: var(--primary-color);
            }

            .flow-steps {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <h1>Berto</h1>
            <ul class="navbar-list">
                <li><a href="index.php">Início</a></li>
                <li><a href="produtos.php">Produtos</a></li>
                <li><a href="servicos_resultados.php">Serviços</a></li>
                <li><a href="suporte.php">Suporte</a></li>
                <li><a href="messages.php">Mensagens</a></li>
                <li><a href="delivery_proof.php" class="active">Entregas</a></li>
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

    <main class="main-container">
        <div class="page-header">
            <h1>Sistema de Provas de Entrega</h1>
            <p>Submeta provas do seu trabalho e revise entregas de forma segura</p>
        </div>

        <div class="flow-explanation">
            <h3>
                <i class="fas fa-route"></i>
                Fluxo Obrigatório de Entregas
            </h3>
            <p style="color: #1E40AF; margin-bottom: 1rem;">Para garantir qualidade e transparência, siga esta sequência obrigatória:</p>
            
            <div class="flow-steps">
                <div class="flow-step">
                    <div class="step-number">1</div>
                    <div class="step-title">Atualização de Progresso</div>
                    <div class="step-desc">Primeira prova obrigatória - mostre que iniciou o trabalho</div>
                </div>
                <div class="flow-step">
                    <div class="step-number">2</div>
                    <div class="step-title">Entrega Parcial</div>
                    <div class="step-desc">Segunda prova obrigatória - entregue parte do trabalho</div>
                </div>
                <div class="flow-step">
                    <div class="step-number">3+</div>
                    <div class="step-title">Progresso/Marcos</div>
                    <div class="step-desc">Continue com atualizações ou marcos concluídos</div>
                </div>
                <div class="flow-step">
                    <div class="step-number">Final</div>
                    <div class="step-title">Entrega Final</div>
                    <div class="step-desc">Última prova - trabalho 100% concluído</div>
                </div>
            </div>
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
            <button class="tab active" onclick="switchTab('submit')">
                <i class="fas fa-upload"></i> Submeter Provas
            </button>
            <button class="tab" onclick="switchTab('review')">
                <i class="fas fa-eye"></i> Revisar Provas (<?= $proofs_to_review->num_rows ?>)
            </button>
            <button class="tab" onclick="switchTab('history')">
                <i class="fas fa-history"></i> Histórico
            </button>
        </div>

        <!-- Tab: Submeter Provas -->
        <div id="submit-tab" class="tab-content active">
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-upload"></i>
                    Submeter Nova Prova (Prestadores)
                </h2>

                <?php if ($escrows->num_rows > 0): ?>
                    <form method="POST" enctype="multipart/form-data" onsubmit="return validateProofForm(this)">
                        <div class="form-group">
                            <label for="escrow_id">Selecionar Projeto</label>
                            <select name="escrow_id" id="escrow_id" required onchange="updateProofTypeOptions(this.value)">
                                <option value="">Escolha um projeto...</option>
                                <?php while ($escrow = $escrows->fetch_assoc()): ?>
                                    <option value="<?= $escrow['id'] ?>">
                                        €<?= number_format($escrow['valor'], 2) ?> - <?= htmlspecialchars($escrow['client_name']) ?>
                                        <?php if ($escrow['service_name']): ?>
                                            (<?= htmlspecialchars($escrow['service_name']) ?>)
                                        <?php endif; ?>
                                        - <?= ucfirst($escrow['status']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="proof_type">Tipo de Prova</label>
                            <select name="proof_type" id="proof_type" required>
                                <option value="">Primeiro selecione um projeto...</option>
                            </select>
                            <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                                <i class="fas fa-info-circle"></i> O tipo disponível depende das provas já submetidas
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="title">Título da Entrega</label>
                            <input type="text" name="title" id="title" placeholder="Ex: Primeira versão do design" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Descrição Detalhada</label>
                            <textarea name="description" id="description" placeholder="Descreva o que foi entregue, progresso realizado, próximos passos..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="external_link">Link Externo (Opcional)</label>
                            <input type="url" name="external_link" id="external_link" placeholder="https://drive.google.com/...">
                        </div>

                        <div class="form-group">
                            <label>Arquivo de Prova (Opcional)</label>
                            <div class="file-upload">
                                <input type="file" name="proof_file" id="proof_file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip,.rar">
                                <div class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Clique para selecionar arquivo (máx. 10MB)</span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="submit_proof" class="submit-btn">
                            <i class="fas fa-paper-plane"></i>
                            Submeter Prova
                        </button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p><strong>Nenhum projeto ativo encontrado</strong></p>
                        <small>Você precisa ter projetos em andamento como <strong>prestador</strong> para submeter provas</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Revisar Provas -->
        <div id="review-tab" class="tab-content">
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-eye"></i>
                    Revisar Provas de Entrega (Clientes)
                </h2>

                <?php if ($proofs_to_review->num_rows > 0): ?>
                    <?php while ($proof = $proofs_to_review->fetch_assoc()): ?>
                        <div class="proof-item">
                            <div class="proof-header">
                                <div>
                                    <div class="proof-title"><?= htmlspecialchars($proof['title']) ?></div>
                                    <div class="proof-type"><?= ucfirst(str_replace('_', ' ', $proof['proof_type'])) ?></div>
                                </div>
                                <div class="proof-status <?= $proof['status'] ?>">
                                    Aguardando Revisão
                                </div>
                            </div>
                            
                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <strong>Prestador:</strong> <?= htmlspecialchars($proof['provider_name']) ?>
                            </p>
                            
                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1rem;">
                                <?= htmlspecialchars($proof['description']) ?>
                            </p>
                            
                            <?php if ($proof['external_link']): ?>
                                <p style="margin-bottom: 1rem;">
                                    <a href="<?= htmlspecialchars($proof['external_link']) ?>" target="_blank" style="color: var(--primary-color);">
                                        <i class="fas fa-external-link-alt"></i> Ver Link Externo
                                    </a>
                                </p>
                            <?php endif; ?>
                            
                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                Submetido em <?= date('d/m/Y H:i', strtotime($proof['submitted_at'])) ?>
                                <?php if ($proof['file_path']): ?>
                                    • <i class="fas fa-paperclip"></i> Arquivo anexado
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" style="margin-top: 1rem;">
                                <input type="hidden" name="proof_id" value="<?= $proof['id'] ?>">
                                
                                <textarea name="client_feedback" class="feedback-textarea" placeholder="Feedback para o prestador (opcional)..."></textarea>
                                
                                <div class="review-actions">
                                    <button type="submit" name="review_proof" value="approve" class="approve-btn" onclick="return confirm('Aprovar esta prova de entrega?')">
                                        <i class="fas fa-check"></i> Aprovar
                                    </button>
                                    <button type="submit" name="review_proof" value="reject" class="reject-btn" onclick="return confirm('Rejeitar esta prova de entrega?')">
                                        <i class="fas fa-times"></i> Rejeitar
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <p>Nenhuma prova aguardando revisão</p>
                        <small>Provas submetidas pelos prestadores aparecerão aqui</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Histórico -->
        <div id="history-tab" class="tab-content">
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Histórico de Entregas
                </h2>

                <?php if ($proofs->num_rows > 0): ?>
                    <?php while ($proof = $proofs->fetch_assoc()): ?>
                        <div class="proof-item">
                            <div class="proof-header">
                                <div>
                                    <div class="proof-title"><?= htmlspecialchars($proof['title']) ?></div>
                                    <div class="proof-type"><?= ucfirst(str_replace('_', ' ', $proof['proof_type'])) ?></div>
                                </div>
                                <div class="proof-status <?= $proof['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $proof['status'])) ?>
                                </div>
                            </div>
                            
                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <strong>Cliente:</strong> <?= htmlspecialchars($proof['client_name']) ?>
                            </p>
                            
                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($proof['description']) ?>
                            </p>
                            
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                Submetido em <?= date('d/m/Y H:i', strtotime($proof['submitted_at'])) ?>
                                <?php if ($proof['file_path']): ?>
                                    • <i class="fas fa-paperclip"></i> Arquivo anexado
                                <?php endif; ?>
                                <?php if ($proof['external_link']): ?>
                                    • <i class="fas fa-link"></i> Link externo
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($proof['client_feedback']): ?>
                                <div style="margin-top: 0.5rem; padding: 0.5rem; background: #f3f4f6; border-radius: 6px; font-size: 0.875rem;">
                                    <strong>Feedback do cliente:</strong><br>
                                    <?= htmlspecialchars($proof['client_feedback']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <p>Nenhuma prova submetida ainda</p>
                        <small>Suas entregas aparecerão aqui</small>
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

        // Update proof type options based on escrow selection
        function updateProofTypeOptions(escrowId) {
            const proofTypeSelect = document.getElementById('proof_type');
            proofTypeSelect.innerHTML = '<option value="">Carregando...</option>';
            
            if (!escrowId) {
                proofTypeSelect.innerHTML = '<option value="">Primeiro selecione um projeto...</option>';
                return;
            }
            
            // Fetch existing proofs for this escrow via AJAX
            fetch(`get_escrow_proofs.php?escrow_id=${escrowId}`)
                .then(response => response.json())
                .then(data => {
                    const hasProgressUpdate = data.some(proof => proof.proof_type === 'progress_update');
                    const hasPartialDelivery = data.some(proof => proof.proof_type === 'partial_delivery');
                    const proofCount = data.length;
                    
                    let options = '<option value="">Selecione o tipo...</option>';
                    
                    if (proofCount === 0) {
                        // First proof must be progress_update
                        options += '<option value="progress_update">Atualização de Progresso (Obrigatório)</option>';
                    } else if (proofCount === 1 && hasProgressUpdate) {
                        // Second proof must be partial_delivery
                        options += '<option value="partial_delivery">Entrega Parcial (Obrigatório)</option>';
                    } else if (proofCount >= 2 && hasPartialDelivery) {
                        // After partial delivery, allow other types
                        options += '<option value="progress_update">Atualização de Progresso</option>';
                        options += '<option value="milestone_completion">Marco Concluído</option>';
                        options += '<option value="final_delivery">Entrega Final</option>';
                    }
                    
                    proofTypeSelect.innerHTML = options;
                })
                .catch(error => {
                    console.error('Error:', error);
                    proofTypeSelect.innerHTML = '<option value="">Erro ao carregar opções</option>';
                });
        }

        // Form validation
        function validateProofForm(form) {
            const escrowId = form.escrow_id.value;
            const proofType = form.proof_type.value;
            const title = form.title.value.trim();
            const description = form.description.value.trim();
            
            if (!escrowId) {
                alert('Por favor, selecione um projeto');
                return false;
            }
            
            if (!proofType) {
                alert('Por favor, selecione o tipo de prova');
                return false;
            }
            
            if (!title || title.length < 5) {
                alert('Por favor, insira um título mais descritivo (mínimo 5 caracteres)');
                return false;
            }
            
            if (!description || description.length < 20) {
                alert('Por favor, forneça uma descrição mais detalhada (mínimo 20 caracteres)');
                return false;
            }
            
            return true;
        }

        // File upload feedback
        document.getElementById('proof_file').addEventListener('change', function(e) {
            const label = document.querySelector('.file-upload-label span');
            if (e.target.files.length > 0) {
                label.textContent = `Arquivo selecionado: ${e.target.files[0].name}`;
            } else {
                label.textContent = 'Clique para selecionar arquivo (máx. 10MB)';
            }
        });
    </script>
</body>
</html>