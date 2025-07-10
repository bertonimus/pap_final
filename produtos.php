<?php
session_start();

// Verifica se o usuário está logado
$nome_usuario = isset($_SESSION["utilizador"]) ? $_SESSION["utilizador"] : "Visitante";

// Conexão com o banco de dados
$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "gestao_utilizadores";

$conn = new mysqli($servidor, $usuario, $senha, $banco);

// Verifica conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Captura o ID do utilizador logado, com verificação
$id_utilizador = isset($_SESSION['id_utilizadores']) ? $_SESSION['id_utilizadores'] : null;
$id_tipos_utilizador = isset($_SESSION['id_tipos_utilizador']) ? $_SESSION['id_tipos_utilizador'] : null;

// Inicializa a variável de pesquisa
$search_query = "";

// Verifica se a pesquisa foi feita
if (isset($_POST['search'])) {
    $search_query = $conn->real_escape_string($_POST['search']);
}

// Consulta para obter produtos
if ($id_tipos_utilizador == 0) {
    $sql = "SELECT * FROM produtos WHERE nome LIKE '%$search_query%'";
} else {
    $sql = "SELECT * FROM produtos WHERE id_utilizador != $id_utilizador AND nome LIKE '%$search_query%'";
}

$result = $conn->query($sql);

// Contagem total de produtos no carrinho
$total_cart_count = 0;
if (isset($_SESSION['carrinho'])) {
    foreach ($_SESSION['carrinho'] as $product_id => $quantity) {
        $total_cart_count += $quantity;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Loja Online Premium</title>
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
            --accent-orange: #f59e0b;
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

        /* Search Section */
        .search-section {
            background: var(--card-background);
            padding: 2rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .search-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
        }

        .search-container input {
            width: 100%;
            padding: 1.25rem 1.5rem 1.25rem 3.5rem;
            font-size: 1.1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            background-color: var(--card-background);
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-soft);
            font-weight: 400;
        }

        .search-container input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-glow);
        }

        .search-container::before {
            content: '\f002';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 3.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
            z-index: 1;
        }

        .search-results {
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 2rem;
            right: 2rem;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-strong);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color var(--transition-speed);
        }

        .search-result-item:hover {
            background-color: rgba(16, 185, 129, 0.05);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 12px;
            margin-right: 1rem;
        }

        .search-result-info {
            flex: 1;
        }

        .search-result-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .search-result-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* Cart Icon */
        .cart-icon {
            position: fixed;
            top: 120px;
            right: 2rem;
            z-index: 999;
        }

        .cart-icon a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            box-shadow: var(--shadow-medium);
            transition: all var(--transition-speed);
            text-decoration: none;
            position: relative;
        }

        .cart-icon a:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-glow);
            border-color: var(--primary-color);
        }

        .cart-icon i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            min-width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: bounce 0.6s ease-out;
        }

        /* Main Content */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .section-header p {
            font-size: 1.125rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .product-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: all var(--transition-speed);
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            group: hover;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-strong);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .product-image {
            position: relative;
            width: 100%;
            height: 280px;
            overflow: hidden;
            background: #f8f9fa;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform var(--transition-speed);
        }

        .product-card:hover .product-image img {
            transform: scale(1.05);
        }

        .product-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity var(--transition-speed);
        }

        .product-card:hover .product-overlay {
            opacity: 1;
        }

        .product-actions {
            display: flex;
            gap: 0.75rem;
        }

        .product-action-btn {
            width: 48px;
            height: 48px;
            background: var(--card-background);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-medium);
        }

        .product-action-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-strong);
        }

        .product-action-btn.primary {
            background: var(--primary-color);
            color: white;
        }

        .product-action-btn.primary:hover {
            background: var(--primary-hover);
        }

        .product-action-btn.contact {
            background: var(--accent-orange);
            color: white;
        }

        .product-action-btn.contact:hover {
            background: #d97706;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.75rem;
        }

        .product-rating i {
            color: #fbbf24;
            font-size: 0.875rem;
        }

        /* No Products */
        .no-products {
            text-align: center;
            padding: 4rem 2rem;
            grid-column: 1 / -1;
        }

        .no-products p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        /* Footer */
        .footer {
            background: var(--text-primary);
            color: white;
            margin-top: 5rem;
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
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-4px); }
            60% { transform: translateY(-2px); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .product-card {
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

            .main-container {
                padding: 2rem 1rem;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 1.5rem;
            }

            .section-header h2 {
                font-size: 2rem;
            }

            .cart-icon {
                right: 1rem;
                top: 100px;
            }

            .search-container {
                padding: 0 1rem;
            }
        }

        @media (max-width: 480px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
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
                <li><a href="produtos.php" class="active">Produtos</a></li>
                <li><a href="servicos_resultados.php">Serviços</a></li>
                <li><a href="suporte.php">Suporte</a></li>
                <li class="messages-dropdown">
                    <a href="#">
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
                        <?php 
                        if (isset($_SESSION['utilizador'])) {
                            echo htmlspecialchars($_SESSION['utilizador']);
                        } else {
                            echo "Visitante";
                        }
                        ?>
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
                    <?php else: ?>
                        <li class="profile-dropdown-list-item">
                            <a href="logintexte.php">
                                <i class="fa-solid fa-sign-in-alt"></i>
                                Login
                            </a>
                        </li>
                        <li class="profile-dropdown-list-item">
                            <a href="register.php">
                                <i class="fa-solid fa-user-plus"></i>
                                Registrar
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Pesquisar produtos..." autocomplete="off">
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <!-- Cart Icon -->
    <div class="cart-icon">
        <a href="<?php echo isset($_SESSION['id_utilizadores']) ? 'cart.php' : 'logintexte.php'; ?>">
            <i class="fas fa-shopping-cart"></i>
            <?php if ($total_cart_count > 0): ?>
                <span class="cart-count"><?php echo $total_cart_count; ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="section-header">
            <h2>Produtos em Destaque</h2>
            <p>Descubra nossa seleção de produtos premium com qualidade excepcional</p>
        </div>

        <!-- Product Grid -->
        <div class="product-grid">
            <?php
            if ($result->num_rows > 0) {
                $delay = 0;
                while ($row = $result->fetch_assoc()) {
                    $product_id = $row['id'];
                    $cart_quantity = $_SESSION['cart'][$product_id] ?? 0;
                    echo "<div class='product-card' style='animation-delay: {$delay}ms'>
                        <div class='product-image'>
                            <img src='utilizador/uploads/{$row['imagem']}' alt='{$row['nome']}'>
                            <div class='product-overlay'>
                                <div class='product-actions'>
                                    <button class='product-action-btn' onclick='viewProduct({$product_id})'>
                                        <i class='fa-solid fa-eye'></i>
                                    </button>";
                    
                    if (isset($_SESSION['id_utilizadores'])) {
                        echo "<button class='product-action-btn contact' onclick='contactSeller({$product_id}, {$row['id_utilizador']})'>
                                        <i class='fa-solid fa-comments'></i>
                                    </button>";
                    }
                    
                    echo "<button class='product-action-btn primary' onclick='addToCart({$product_id})'>
                                        <i class='fa-solid fa-shopping-cart'></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class='product-info'>
                            <h3 class='product-name'>{$row['nome']}</h3>
                            <p class='product-price'>{$row['preco']} €</p>
                            
                        </div>
                    </div>";
                    $delay += 100;
                }
            } else {
                echo "<div class='no-products'>
                        <i class='fa-solid fa-box-open' style='font-size: 4rem; color: var(--text-secondary); margin-bottom: 1rem;'></i>
                        <p>Nenhum produto encontrado.</p>
                        <p style='font-size: 1rem; color: var(--text-secondary);'>Tente ajustar sua pesquisa ou explore outras categorias.</p>
                      </div>";
            }
            ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="footer-col">
                    <h4>Empresa</h4>
                    <ul>
                        <li><a href="#">Sobre Nós</a></li>
                        <li><a href="#">Nossos Serviços</a></li>
                        <li><a href="#">Política de Privacidade</a></li>
                        <li><a href="#">Programa de Afiliados</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Ajuda</h4>
                    <ul>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Envio</a></li>
                        <li><a href="#">Devoluções</a></li>
                        <li><a href="#">Status do Pedido</a></li>
                        <li><a href="#">Opções de Pagamento</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Loja Online</h4>
                    <ul>
                        <li><a href="#">Relógios</a></li>
                        <li><a href="#">Bolsas</a></li>
                        <li><a href="#">Sapatos</a></li>
                        <li><a href="#">Roupas</a></li>
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

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value;

            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`search_products.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        searchResults.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(product => {
                                const div = document.createElement('div');
                                div.className = 'search-result-item';
                                div.innerHTML = `
                                    <img src="utilizador/uploads/${product.imagem}" alt="${product.nome}">
                                    <div class="search-result-info">
                                        <div class="search-result-name">${product.nome}</div>
                                        <div class="search-result-price">${product.preco} €</div>
                                    </div>
                                `;
                                div.onclick = () => window.location.href = `detalhes_produto.php?id=${product.id}`;
                                searchResults.appendChild(div);
                            });
                            searchResults.style.display = 'block';
                        } else {
                            searchResults.innerHTML = '<div class="search-result-item"><div class="search-result-info"><div class="search-result-name">Nenhum produto encontrado</div></div></div>';
                            searchResults.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Erro na pesquisa:', error);
                        searchResults.style.display = 'none';
                    });
            }, 300);
        });

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchResults.contains(e.target) && e.target !== searchInput) {
                searchResults.style.display = 'none';
            }
        });

        // Product actions
        function viewProduct(productId) {
            window.location.href = `detalhes_produto.php?id=${productId}`;
        }

        function contactSeller(productId, sellerId) {
            <?php if (isset($_SESSION['id_utilizadores'])): ?>
                window.location.href = `product_messages.php?destinatario_id=${sellerId}&produto_id=${productId}`;
            <?php else: ?>
                window.location.href = 'logintexte.php';
            <?php endif; ?>
        }

        function addToCart(productId) {
            <?php if (isset($_SESSION['id_utilizadores'])): ?>
                fetch('cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=add&product_id=${productId}&quantity=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cartCount = document.querySelector('.cart-count');
                        if (cartCount) {
                            cartCount.textContent = parseInt(cartCount.textContent) + 1;
                        } else {
                            // Create cart count if it doesn't exist
                            const cartIcon = document.querySelector('.cart-icon a');
                            const newCount = document.createElement('span');
                            newCount.className = 'cart-count';
                            newCount.textContent = '1';
                            cartIcon.appendChild(newCount);
                        }
                        
                        // Show success notification
                        showNotification('Produto adicionado ao carrinho!', 'success');
                    } else {
                        showNotification('Erro ao adicionar produto ao carrinho', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showNotification('Erro ao adicionar produto ao carrinho', 'error');
                });
            <?php else: ?>
                window.location.href = 'logintexte.php';
            <?php endif; ?>
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: ${type === 'success' ? 'var(--primary-color)' : '#ef4444'};
                color: white;
                border-radius: 12px;
                box-shadow: var(--shadow-strong);
                z-index: 10000;
                font-weight: 500;
                animation: slideIn 0.3s ease-out;
            `;
            notification.textContent = message;
            
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