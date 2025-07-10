<?php
session_start();

// Database connection using variables from the first code
$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "gestao_utilizadores";

$conn = new mysqli($servidor, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed']));
}

// Check if user is logged in
if (!isset($_SESSION['id_utilizadores'])) {
    header('Location: logintexte.php');
    exit();
}

$usuario_id = $_SESSION['id_utilizadores'];

// Get user info
$stmt = $conn->prepare("SELECT utilizador FROM utilizadores WHERE id_utilizadores = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $nome_usuario = $row['utilizador'];
} else {
    $nome_usuario = "Usuário";
}
$stmt->close();

// Get cart items
$cart_items = [];
$sql = "SELECT c.quantidade, p.id, p.nome, p.preco, p.imagem, p.quantidade as stock_disponivel
        FROM carrinho c 
        JOIN produtos p ON c.produto_id = p.id 
        WHERE c.usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$total_geral = 0;
while ($row = $result->fetch_assoc()) {
    $row['total'] = $row['preco'] * $row['quantidade'];
    $total_geral += $row['total'];
    $cart_items[] = $row;
}
$stmt->close();

// Check if cart is empty
if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

// Handle form submission
$success = false;
$error_message = '';
function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received");
    error_log("POST data: " . print_r($_POST, true));
    debug_to_console("ola");
    if (isset($_POST['confirm_payment'])) {
        debug_to_console("adeus");
        error_log("Confirm payment button clicked");
        try {
            $conn->begin_transaction();

            // Verify stock availability
            foreach ($cart_items as $item) {
                $stmt = $conn->prepare("SELECT quantidade FROM produtos WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $current_stock = $result->fetch_assoc();
                $stmt->close();

                if ($current_stock['quantidade'] < $item['quantidade']) {
                    throw new Exception("Stock insuficiente para o produto: " . $item['nome']);
                }
            }

            // Get payment details
            $nome_completo = $_POST['cardholder'];
            $numero_cartao = $_POST['card_number'];
            $validade = $_POST['expiry'];
            $cvv = $_POST['cvv'];
            $endereco = $_POST['address'];
            $cidade = $_POST['city'];
            $codigo_postal = $_POST['postal_code'];

            // Create order
            $stmt = $conn->prepare("INSERT INTO pedidos (usuario_id, total, status, created_at) VALUES (?, ?, 'processando', NOW())");
            $stmt->bind_param("id", $usuario_id, $total_geral);
            $stmt->execute();
            $pedido_id = $conn->insert_id;
            $stmt->close();

            // Add order items and update stock
            foreach ($cart_items as $item) {
                // Add to order items
                $stmt = $conn->prepare("INSERT INTO pedido_items (pedido_id, produto_id, quantidade, preco) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiid", $pedido_id, $item['id'], $item['quantidade'], $item['preco']);
                $stmt->execute();
                $stmt->close();

                // Update product stock
                $stmt = $conn->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");
                $stmt->bind_param("ii", $item['quantidade'], $item['id']);
                $stmt->execute();
                $stmt->close();
            }

            

            // Clear cart
            $stmt = $conn->prepare("DELETE FROM carrinho WHERE usuario_id = ?");
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $stmt->close();

            // Transfer money to sellers' balances
            foreach ($cart_items as $item) {
                // Get product owner
                $stmt = $conn->prepare("SELECT id_utilizador FROM produtos WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
                $seller_result = $stmt->get_result();
                $seller_data = $seller_result->fetch_assoc();
                $stmt->close();

                if ($seller_data) {
                    $seller_id = $seller_data['id_utilizador'];
                    $item_total = $item['total'];

                    // Check if seller has a balance record
                    $stmt = $conn->prepare("SELECT user_id FROM user_balances WHERE user_id = ?");
                    $stmt->bind_param("i", $seller_id);
                    $stmt->execute();
                    $balance_exists = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$balance_exists) {
                        // Create balance record for seller
                        $stmt = $conn->prepare("
                        INSERT INTO user_balances (user_id, total_balance, available_balance, pending_balance, created_at, last_updated) 
                        VALUES (?, 0, 0, 0, NOW(), NOW())
                    ");
                        $stmt->bind_param("i", $seller_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // Add money to seller's balance
                    $stmt = $conn->prepare("
                    UPDATE user_balances 
                    SET total_balance = total_balance + ?, 
                        available_balance = available_balance + ?,
                        last_updated = NOW() 
                    WHERE user_id = ?
                ");
                    $stmt->bind_param("ddi", $item_total, $item_total, $seller_id);
                    $stmt->execute();
                    $stmt->close();

                    // Record transaction for seller
                    $stmt = $conn->prepare("
                    INSERT INTO user_balance_transactions 
                    (user_id, amount, transaction_type, description, status, created_at) 
                    VALUES (?, ?, 'sale', ?, 'completed', NOW())
                ");
                    $description = "Venda do produto: " . $item['nome'] . " (Qtd: " . $item['quantidade'] . ")";
                    $stmt->bind_param("ids", $seller_id, $item_total, $description);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();
            $success = true;

            error_log("Payment processed successfully");

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
            error_log("Payment error: " . $e->getMessage());
        }
    } else {
        error_log("confirm_payment not set in POST");
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Checkout Seguro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            --danger-color: #ef4444;
            --danger-hover: #dc2626;
            --success-color: #10b981;
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

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            align-items: start;
        }

        .checkout-section {
            background: var(--card-background);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .checkout-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #D1FAE5;
            color: #065F46;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Order Summary */
        .order-summary {
            background: var(--card-background);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            position: sticky;
            top: 100px;
        }

        .order-summary h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .order-item {
            display: flex;
            gap: 1rem;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .order-item-details {
            flex: 1;
        }

        .order-item-details h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .order-item-details p {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .order-item-price {
            font-weight: 600;
            color: var(--primary-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--primary-color);
            margin-top: 0.5rem;
            padding-top: 1rem;
        }

        /* Form Styles */
        .payment-form {
            margin-bottom: 2rem;
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
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all var(--transition-speed);
            background: var(--card-background);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .payment-btn {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .payment-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Success Message */
        .success-message {
            text-align: center;
            padding: 3rem 2rem;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .success-icon i {
            font-size: 2rem;
            color: white;
        }

        .success-message h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .success-message p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all var(--transition-speed);
        }

        .back-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            text-decoration: none;
            color: white;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: #991b1b;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success-color);
            color: #065f46;
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
                grid-template-columns: 1fr;
                padding: 0 1rem;
                margin: 2rem auto;
            }

            .order-summary {
                position: static;
                order: -1;
            }

            .checkout-section {
                padding: 1.5rem;
            }

            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }

            .checkout-header h1 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .navbar h1 {
                font-size: 1.5rem;
            }

            .checkout-section {
                padding: 1rem;
            }

            .order-summary {
                padding: 1rem;
            }
        }

        /* Animations */
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

        .checkout-section,
        .order-summary {
            animation: fadeIn 0.6s ease-out forwards;
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
                <li><a href="serviços.php">Serviços</a></li>
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
                        <a href="#">
                            <i class="fa-solid fa-sliders"></i>
                            Configurações
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
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-container">
        <?php if ($success): ?>
            <div class="checkout-section" style="grid-column: 1 / -1;">
                <div class="success-message">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2>Pedido Confirmado!</h2>
                    <p>Seu pedido foi processado com sucesso. Você receberá um email de confirmação em breve com os detalhes
                        da entrega.</p>
                    <a href="produtos.php" class="back-btn">
                        <i class="fas fa-shopping-bag"></i>
                        Continuar Comprando
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-section">
                <div class="checkout-header">
                    <h1>Finalizar Compra</h1>
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        Pagamento Seguro
                    </div>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="payment-form" id="checkout-form">
                    <h3 style="margin-bottom: 1rem; color: var(--text-primary);">Informações de Entrega</h3>

                    <div class="form-group">
                        <label for="cardholder">Nome Completo</label>
                        <input type="text" id="cardholder" name="cardholder" placeholder="Seu nome completo"
                            value="<?php echo htmlspecialchars($nome_usuario); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="address">Endereço</label>
                        <input type="text" id="address" name="address" placeholder="Rua, número, andar"
                            value="Rua Exemplo, 123" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">Cidade</label>
                            <input type="text" id="city" name="city" placeholder="Cidade" value="Lisboa" required>
                        </div>
                        <div class="form-group">
                            <label for="postal_code">Código Postal</label>
                            <input type="text" id="postal_code" name="postal_code" placeholder="0000-000" value="1000-001"
                                required>
                        </div>
                    </div>

                    <h3 style="margin: 2rem 0 1rem; color: var(--text-primary);">Informações de Pagamento</h3>

                    <div class="form-group">
                        <label for="card_number">Número do Cartão</label>
                        <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456"
                            value="1234 5678 9012 3456" required maxlength="19">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry">Validade</label>
                            <input type="text" id="expiry" name="expiry" placeholder="MM/AA" value="12/25" required
                                maxlength="5">
                        </div>
                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <input type="text" id="cvv" name="cvv" placeholder="123" value="123" required maxlength="3">
                        </div>
                    </div>
                    <input type="hidden" name="confirm_payment" value="1">
                    <button type="submit" name="confirm_payment" class="payment-btn" id="payment-btn">
                        <i class="fas fa-credit-card"></i>
                        Confirmar Pagamento - €<?php echo number_format($total_geral, 2); ?>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1rem;">
                    <a href="cart.php" style="color: var(--text-secondary); text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Voltar ao carrinho
                    </a>
                </div>
            </div>

            <div class="order-summary">
                <h3>Resumo do Pedido</h3>

                <div style="max-height: 300px; overflow-y: auto; margin-bottom: 1rem;">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="order-item">
                            <img src="utilizador/uploads/<?php echo htmlspecialchars($item['imagem']); ?>"
                                alt="<?php echo htmlspecialchars($item['nome']); ?>">
                            <div class="order-item-details">
                                <h4><?php echo htmlspecialchars($item['nome']); ?></h4>
                                <p>Quantidade: <?php echo $item['quantidade']; ?></p>
                            </div>
                            <div class="order-item-price">
                                €<?php echo number_format($item['total'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>€<?php echo number_format($total_geral, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Entrega</span>
                    <span>€0.00</span>
                </div>
                <div class="summary-row">
                    <span>Taxa de Processamento</span>
                    <span>€0.00</span>
                </div>
                <div class="summary-row">
                    <span><strong>Total</strong></span>
                    <span><strong>€<?php echo number_format($total_geral, 2); ?></strong></span>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Profile dropdown toggle
        function toggle() {
            const dropdown = document.querySelector('.profile-dropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const dropdown = document.querySelector('.profile-dropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Format card number input
        document.getElementById('card_number').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Format expiry input
        document.getElementById('expiry').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // CVV input restriction
        document.getElementById('cvv').addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
        });

        // Format postal code
        document.getElementById('postal_code').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 4) {
                value = value.substring(0, 4) + '-' + value.substring(4, 7);
            }
            e.target.value = value;
        });

        // Form validation
        document.getElementById('checkout-form').addEventListener('submit', function (e) {
            console.log('Form submitted');
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const expiry = document.getElementById('expiry').value;
            const cvv = document.getElementById('cvv').value;
            const name = document.getElementById('cardholder').value;
            const address = document.getElementById('address').value;
            const city = document.getElementById('city').value;
            const postalCode = document.getElementById('postal_code').value;

            console.log('Validating form data...');

            if (cardNumber.length !== 16) {
                e.preventDefault();
                showNotification('Número do cartão deve ter 16 dígitos', 'error');
                console.log('Card number validation failed');
                return;
            }

            if (expiry.length !== 5 || !expiry.includes('/')) {
                e.preventDefault();
                showNotification('Validade deve estar no formato MM/AA', 'error');
                console.log('Expiry validation failed');
                return;
            }

            if (cvv.length !== 3) {
                e.preventDefault();
                showNotification('CVV deve ter 3 dígitos', 'error');
                console.log('CVV validation failed');
                return;
            }

            if (!name.trim() || !address.trim() || !city.trim() || !postalCode.trim()) {
                e.preventDefault();
                showNotification('Todos os campos de entrega são obrigatórios', 'error');
                console.log('Required fields validation failed');
                return;
            }

            console.log('All validations passed, submitting form...');

            // Disable button to prevent double submission
            const btn = document.getElementById('payment-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

            // Show processing message
            showNotification('Processando pagamento...', 'info');
        });

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: ${type === 'success' ? 'var(--success-color)' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                border-radius: 12px;
                box-shadow: var(--shadow-strong);
                z-index: 10000;
                font-weight: 500;
                animation: slideIn 0.3s ease-out;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            `;

            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            notification.innerHTML = `<i class="fas ${icon}"></i>${message}`;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
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
    </script>
</body>

</html>

<?php
$conn->close();
?>