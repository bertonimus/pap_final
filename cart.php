<?php
session_start();

// Database connection
$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "gestao_utilizadores";


$conn = new mysqli($servidor, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed']));
}

// Verifica se o usuário está logado
if (!isset($_SESSION['id_utilizadores'])) {
    header('Location: logintexte.php');
    exit();
}

$usuario_id = $_SESSION['id_utilizadores'];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;

    switch ($action) {
        case 'add':
            // Verifica a quantidade disponível
            $stmt = $conn->prepare("SELECT quantidade FROM produtos WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();

            if ($product) {
                $available_quantity = $product['quantidade'];

                // Verifica se a quantidade desejada é maior que a disponível
                if ($quantity > $available_quantity) {
                    echo json_encode(['success' => false, 'error' => 'Quantidade excede a disponível']);
                    exit();
                }

                // Adiciona ao carrinho
                $stmt = $conn->prepare("INSERT INTO carrinho (usuario_id, produto_id, quantidade) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantidade = quantidade + ?");
                $stmt->bind_param("iiii", $usuario_id, $product_id, $quantity, $quantity);
                $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado']);
            }
            break;

        case 'update':
            // Verifica a quantidade disponível antes de atualizar
            $stmt = $conn->prepare("SELECT quantidade FROM produtos WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();

            if ($product) {
                $available_quantity = $product['quantidade'];

                // Verifica se a nova quantidade desejada é maior que a disponível
                if ($quantity > $available_quantity) {
                    echo json_encode(['success' => false, 'error' => 'Quantidade excede a disponível']);
                    exit();
                }

                if ($quantity > 0) {
                    $stmt = $conn->prepare("UPDATE carrinho SET quantidade = ? WHERE usuario_id = ? AND produto_id = ?");
                    $stmt->bind_param("iii", $quantity, $usuario_id, $product_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("DELETE FROM carrinho WHERE usuario_id = ? AND produto_id = ?");
                    $stmt->bind_param("ii", $usuario_id, $product_id);
                    $stmt->execute();
                    $stmt->close();
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado']);
            }
            break;

        case 'remove':
            $stmt = $conn->prepare("DELETE FROM carrinho WHERE usuario_id = ? AND produto_id = ?");
            $stmt->bind_param("ii", $usuario_id, $product_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Handle GET requests (display cart)
$cart_items = [];
$sql = "SELECT c.quantidade, p.id, p.nome, p.preco, p.imagem 
        FROM carrinho c 
        JOIN produtos p ON c.produto_id = p.id 
        WHERE c.usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['total'] = $row['preco'] * $row['quantidade'];
    $cart_items[] = $row;
}

// Buscar nome do usuário
$stmt = $conn->prepare("SELECT utilizador FROM utilizadores WHERE id_utilizadores = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $nome_usuario = $row['utilizador'];
} else {
    $nome_usuario = "Usuário"; // Nome padrão caso não encontre
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Carrinho de Compras</title>
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

        /* Cart Container */
        .cart-container {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 2rem;
        }

        .cart-header {
            background: var(--card-background);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            text-align: center;
        }

        .cart-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .cart-header p {
            color: var(--text-secondary);
            font-size: 1.125rem;
        }

        /* Cart Items */
        .cart-item {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-soft);
            transition: all var(--transition-speed);
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 2rem;
            align-items: center;
        }

        .cart-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .cart-item img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
        }

        .cart-item-details {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .cart-item-details h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .price-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .price-info .total-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.25rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .quantity-btn:hover {
            background: var(--primary-hover);
            transform: scale(1.1);
        }

        .quantity {
            font-size: 1.25rem;
            font-weight: 600;
            min-width: 3rem;
            text-align: center;
            padding: 0.5rem 1rem;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 12px;
            color: var(--primary-color);
        }

        .remove-btn {
            background: var(--danger-color) !important;
            padding: 0.75rem 1.5rem;
            border-radius: 12px !important;
            width: auto !important;
            height: auto !important;
            font-weight: 500;
            gap: 0.5rem;
            display: flex;
            align-items: center;
        }

        .remove-btn:hover {
            background: var(--danger-hover) !important;
        }

        /* Cart Summary */
        .cart-summary {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 100px;
        }

        .cart-summary h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .cart-total {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            text-align: center;
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(16, 185, 129, 0.1);
            border-radius: var(--border-radius);
        }

        .checkout-btn {
            width: 100%;
            padding: 1.25rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-soft);
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-background);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .empty-cart-icon {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .empty-cart h3 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .empty-cart p {
            font-size: 1.125rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .continue-shopping {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-soft);
        }

        .continue-shopping:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            text-decoration: none;
            color: white;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cart-item {
            animation: fadeIn 0.6s ease-out forwards;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar-container {
                padding: 1rem;
            }

            .navbar-list {
                display: none;
            }

            .cart-container {
                padding: 0 1rem;
                margin: 2rem auto;
            }

            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 1.5rem;
                padding: 1.5rem;
            }

            .cart-item img {
                margin: 0 auto;
                width: 100px;
                height: 100px;
            }

            .quantity-controls {
                justify-content: center;
            }

            .cart-header h2 {
                font-size: 2rem;
            }

            .cart-total {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .cart-header {
                padding: 1.5rem;
            }

            .cart-item {
                padding: 1rem;
            }

            .navbar h1 {
                font-size: 1.5rem;
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

    <div class="cart-container">
        <div class="cart-header">
            <h2>Carrinho de Compras</h2>
            <p>Revise seus itens antes de finalizar a compra</p>
        </div>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart empty-cart-icon"></i>
                <h3>Seu carrinho está vazio</h3>
                <p>Adicione alguns produtos incríveis ao seu carrinho para começar suas compras</p>
                <a href="produtos.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i>
                    Continuar Comprando
                </a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 2rem; align-items: start;">
                <div>
                    <?php
                    $grouped_items = [];
                    foreach ($cart_items as $item) {
                        if (!isset($grouped_items[$item['id']])) {
                            $grouped_items[$item['id']] = $item;
                        } else {
                            $grouped_items[$item['id']]['quantidade'] += $item['quantidade'];
                            $grouped_items[$item['id']]['total'] += $item['total'];
                        }
                    }
                    $delay = 0;
                    foreach ($grouped_items as $item): ?>
                        <div class="cart-item" data-id="<?php echo $item['id']; ?>" style="animation-delay: <?php echo $delay; ?>ms">
                            <img src="utilizador/uploads/<?php echo $item['imagem']; ?>" alt="<?php echo $item['nome']; ?>">
                            <div class="cart-item-details">
                                <h3><?php echo $item['nome']; ?></h3>
                                <div class="price-info">
                                    <span>Preço unitário: €<?php echo number_format($item['preco'], 2); ?></span>
                                    <span>•</span>
                                    <span class="total-price">Total: €<?php echo number_format($item['total'], 2); ?></span>
                                </div>
                            </div>
                            <div class="quantity-controls">
                                <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity"><?php echo $item['quantidade']; ?></span>
                                <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="remove-btn" onclick="removeItem(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                    Remover
                                </button>
                            </div>
                        </div>
                    <?php 
                        $delay += 100;
                    endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h3>Resumo do Pedido</h3>
                    <div class="cart-total">
                        Total: €<?php echo number_format(array_sum(array_column($grouped_items, 'total')), 2); ?>
                    </div>
                    <button class="checkout-btn" onclick="checkout()">
                        <i class="fas fa-credit-card"></i>
                        Finalizar Compra
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Profile dropdown toggle
        function toggle() {
            const dropdown = document.querySelector('.profile-dropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.profile-dropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        function updateQuantity(productId, change) {
            const item = document.querySelector(`.cart-item[data-id="${productId}"]`);
            const quantitySpan = item.querySelector('.quantity');
            let newQuantity = parseInt(quantitySpan.textContent) + change;

            if (newQuantity < 1) newQuantity = 1;

            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update&product_id=${productId}&quantity=${newQuantity}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Carrinho atualizado!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showNotification('Erro ao atualizar carrinho', 'error');
                });
        }

        function removeItem(productId) {
            if (confirm('Tem certeza que deseja remover este item do carrinho?')) {
                fetch('cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove&product_id=${productId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('Item removido do carrinho!', 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification('Erro ao remover item', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showNotification('Erro ao remover item', 'error');
                    });
            }
        }

        function checkout() {
            showNotification('Redirecionando para checkout...', 'info');
            setTimeout(() => {
                window.location.href = 'checkout_produtos.php';
            }, 1000);
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: ${type === 'success' ? 'var(--primary-color)' : type === 'error' ? '#ef4444' : '#3b82f6'};
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