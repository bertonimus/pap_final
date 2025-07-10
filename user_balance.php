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

// Processar saque
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    try {
        $withdraw_amount = (float)$_POST['withdraw_amount'];
        
        if ($withdraw_amount <= 0) {
            throw new Exception("Valor de saque deve ser maior que zero");
        }
        
        // Verificar saldo disponível
        $stmt = $conn->prepare("SELECT available_balance FROM user_balances WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $balance_result = $stmt->get_result()->fetch_assoc();
        
        if (!$balance_result || $balance_result['available_balance'] < $withdraw_amount) {
            throw new Exception("Saldo insuficiente para saque");
        }
        
        $conn->begin_transaction();
        
        // Registrar transação de saque
        $stmt = $conn->prepare("
            INSERT INTO user_balance_transactions 
            (user_id, amount, transaction_type, description, status, created_at) 
            VALUES (?, ?, 'withdrawal', ?, 'pending', NOW())
        ");
        $description = "Solicitação de saque - €" . number_format($withdraw_amount, 2);
        $negative_amount = -$withdraw_amount;
        $stmt->bind_param("ids", $user_id, $negative_amount, $description);
        $stmt->execute();
        
        // Atualizar saldo
        $stmt = $conn->prepare("
            UPDATE user_balances 
            SET available_balance = available_balance - ?, 
                pending_balance = pending_balance + ?,
                last_updated = NOW() 
            WHERE user_id = ?
        ");
        $stmt->bind_param("ddi", $withdraw_amount, $withdraw_amount, $user_id);
        $stmt->execute();
        
        $conn->commit();
        $success_message = "Solicitação de saque enviada com sucesso! Será processada em até 3 dias úteis.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Obter saldo atual
$stmt = $conn->prepare("
    SELECT total_balance, available_balance, pending_balance 
    FROM user_balances 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance_result = $stmt->get_result()->fetch_assoc();

$total_balance = $balance_result['total_balance'] ?? 0;
$available_balance = $balance_result['available_balance'] ?? 0;
$pending_balance = $balance_result['pending_balance'] ?? 0;

// Obter histórico de transações
$stmt = $conn->prepare("
    SELECT * FROM user_balance_transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Meu Saldo</title>
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
            --shadow-soft: 0 2px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 4px 25px rgba(0, 0, 0, 0.12);
            --shadow-strong: 0 8px 40px rgba(0, 0, 0, 0.15);
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

        .balance-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .balance-card {
            background: var(--card-background);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        .balance-card.total::before {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }

        .balance-card.pending::before {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .balance-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .balance-card.total i {
            color: #3b82f6;
        }

        .balance-card.pending i {
            color: #f59e0b;
        }

        .balance-amount {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .balance-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .withdraw-section {
            background: var(--card-background);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            margin-bottom: 3rem;
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

        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all var(--transition-speed);
            background: var(--card-background);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .withdraw-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
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

        .withdraw-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .transactions-section {
            background: var(--card-background);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: background-color var(--transition-speed);
        }

        .transaction-item:hover {
            background-color: #f9fafb;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-description {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .transaction-date {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .transaction-amount {
            font-weight: 700;
            font-size: 1.125rem;
        }

        .transaction-amount.positive {
            color: var(--success-color);
        }

        .transaction-amount.negative {
            color: var(--error-color);
        }

        .transaction-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 1rem;
        }

        .transaction-status.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .transaction-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .transaction-status.failed {
            background: #fee2e2;
            color: #991b1b;
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

            .balance-cards {
                grid-template-columns: 1fr;
            }

            .transaction-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .transaction-status {
                margin-left: 0;
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
            <li><a href="user_balance.php" class="active">Saldo</a></li>
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
    </nav>

    <main class="main-container">
        <div class="page-header">
            <h1>Meu Saldo</h1>
            <p>Gerencie seus ganhos e solicite saques</p>
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

        <!-- Balance Cards -->
        <div class="balance-cards">
            <div class="balance-card">
                <i class="fas fa-wallet"></i>
                <div class="balance-amount">€<?= number_format($available_balance, 2) ?></div>
                <div class="balance-label">Saldo Disponível</div>
            </div>
            
            <div class="balance-card total">
                <i class="fas fa-chart-line"></i>
                <div class="balance-amount">€<?= number_format($total_balance, 2) ?></div>
                <div class="balance-label">Total Ganho</div>
            </div>
            
            <div class="balance-card pending">
                <i class="fas fa-clock"></i>
                <div class="balance-amount">€<?= number_format($pending_balance, 2) ?></div>
                <div class="balance-label">Pendente</div>
            </div>
        </div>

        <!-- Withdraw Section -->
        <div class="withdraw-section">
            <h2 class="section-title">
                <i class="fas fa-money-bill-wave"></i>
                Solicitar Saque
            </h2>
            
            <form method="POST" onsubmit="return validateWithdraw(this)">
                <div class="form-group">
                    <label for="withdraw_amount">Valor do Saque</label>
                    <input type="number" name="withdraw_amount" id="withdraw_amount" 
                           step="0.01" min="1" max="<?= $available_balance ?>" 
                           placeholder="0.00" required>
                    <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                        Saldo disponível: €<?= number_format($available_balance, 2) ?>
                    </small>
                </div>
                
                <button type="submit" name="withdraw" class="withdraw-btn">
                    <i class="fas fa-paper-plane"></i>
                    Solicitar Saque
                </button>
            </form>
        </div>

        <!-- Transactions History -->
        <div class="transactions-section">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Histórico de Transações
            </h2>

            <?php if ($transactions->num_rows > 0): ?>
                <?php while ($transaction = $transactions->fetch_assoc()): ?>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-description">
                                <?= htmlspecialchars($transaction['description']) ?>
                            </div>
                            <div class="transaction-date">
                                <?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center;">
                            <div class="transaction-amount <?= $transaction['amount'] >= 0 ? 'positive' : 'negative' ?>">
                                <?= $transaction['amount'] >= 0 ? '+' : '' ?>€<?= number_format(abs($transaction['amount']), 2) ?>
                            </div>
                            <div class="transaction-status <?= $transaction['status'] ?>">
                                <?= ucfirst($transaction['status']) ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>Nenhuma transação encontrada</p>
                    <small>Suas transações aparecerão aqui</small>
                </div>
            <?php endif; ?>
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

        function validateWithdraw(form) {
            const amount = parseFloat(form.withdraw_amount.value);
            const available = <?= $available_balance ?>;
            
            if (amount <= 0) {
                alert('O valor deve ser maior que zero');
                return false;
            }
            
            if (amount > available) {
                alert('Valor excede o saldo disponível');
                return false;
            }
            
            if (amount < 1) {
                alert('Valor mínimo para saque é €1.00');
                return false;
            }
            
            return confirm(`Confirma o saque de €${amount.toFixed(2)}?`);
        }
    </script>
</body>
</html>