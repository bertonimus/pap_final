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

// Buscar nome do usuário
if (isset($_SESSION['id_utilizadores'])) {
    $stmt = $conn->prepare("SELECT utilizador FROM utilizadores WHERE id_utilizadores = ?");
    $usuario_id = $_SESSION['id_utilizadores'];
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $nome_usuario = $row['utilizador'];
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Sobre - Berto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />
     <link rel="icon" type="image/png" href="../berto.png" />
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 100%;
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
            z-index: 1000;
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
            width: 100%;
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

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* About Hero */
        .about-hero {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95)), 
                        url('https://images.pexels.com/photos/3184465/pexels-photo-3184465.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 6rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
            animation: float 20s ease-in-out infinite;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .hero-content p {
            font-size: 1.3rem;
            opacity: 0.95;
            font-weight: 400;
        }

        /* Our Story */
        .our-story {
            padding: 6rem 0;
            background: var(--card-background);
        }

        .story-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .story-text h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--text-primary);
        }

        .story-text p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }

        .story-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
        }

        /* Mission Vision Values */
        .mission-vision-values {
            padding: 6rem 0;
            background: var(--background-color);
        }

        .mission-vision-values h2 {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 4rem;
            color: var(--text-primary);
        }

        .pillars {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .pillar {
            background: var(--card-background);
            padding: 3rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all var(--transition-speed);
        }

        .pillar:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-strong);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .pillar-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
        }

        .pillar-icon i {
            font-size: 2rem;
        }

        .pillar h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .pillar p {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Statistics */
        .statistics {
            padding: 6rem 0;
            background: var(--card-background);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            background: var(--background-color);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-soft);
            transition: all var(--transition-speed);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* How We Work */
        .how-we-work {
            padding: 6rem 0;
            background: var(--background-color);
        }

        .how-we-work h2 {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 4rem;
            color: var(--text-primary);
        }

        .work-process {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .process-step {
            background: var(--card-background);
            padding: 3rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all var(--transition-speed);
        }

        .process-step:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-strong);
        }

        .step-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
        }

        .step-icon i {
            font-size: 1.5rem;
        }

        .process-step h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .process-step p {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Security Trust */
        .security-trust {
            padding: 6rem 0;
            background: var(--card-background);
        }

        .security-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .security-text h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--text-primary);
        }

        .security-features {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--background-color);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
        }

        .feature:hover {
            background: rgba(16, 185, 129, 0.05);
            border-color: var(--primary-color);
        }

        .feature i {
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        .feature span {
            color: var(--text-primary);
            font-weight: 500;
        }

        .security-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
        }

        /* Location */
        .location {
            padding: 6rem 0;
            background: var(--background-color);
        }

        .location h2 {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 4rem;
            color: var(--text-primary);
        }

        .location-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .location-info {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .location-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 2rem;
            background: var(--card-background);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-soft);
        }

        .location-item i {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-top: 0.25rem;
        }

        .location-item h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .location-item p {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .location-map {
            background: var(--card-background);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-soft);
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .map-placeholder {
            text-align: center;
            color: var(--text-secondary);
        }

        .map-placeholder i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        /* CTA */
        .cta {
            padding: 6rem 0;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 20s ease-in-out infinite reverse;
        }

        .cta .container {
            position: relative;
            z-index: 1;
        }

        .cta h2 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cta p {
            font-size: 1.3rem;
            margin-bottom: 3rem;
            opacity: 0.95;
        }

        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
        }

        .btn-primary {
            background: white;
            color: var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--background-color);
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border-color: white;
        }

        .btn-secondary:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        /* Footer */
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
            text-decoration: none;
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
            0%, 100% { 
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

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
            }

            .navbar-list {
                display: none;
            }

            .about-hero {
                padding: 4rem 1rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .story-content,
            .security-content,
            .location-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .pillars,
            .work-process {
                grid-template-columns: 1fr;
            }

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

            .mission-vision-values h2,
            .how-we-work h2,
            .location h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .pillar,
            .process-step {
                padding: 2rem 1.5rem;
            }

            .story-text h2,
            .security-text h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar">
        <h1>Berto</h1>
        <ul class="navbar-list">
            <li><a href="index.php">Início</a></li>
            <li><a href="produtos.php">Produtos</a></li>
            <li><a href="serviços_login.php">Serviços</a></li>
            <li><a href="suporte.php">Suporte</a></li>
            <li><a href="messages.php">Mensagens</a></li>
            <li><a href="#" class="active">Sobre</a></li>
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
                        <a href="registop2.php">
                            <i class="fa-solid fa-user-plus"></i>
                            Registrar
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <main>
        <!-- Hero Section -->
        <section class="about-hero">
            <div class="hero-content">
                <h1>Sobre o Berto</h1>
                <p>Conectando pessoas e transformando a forma como os serviços são encontrados e prestados</p>
            </div>
        </section>

        <!-- Nossa História -->
        <section class="our-story">
            <div class="container">
                <div class="story-content">
                    <div class="story-text">
                        <h2>Nossa História</h2>
                        <p>
                            O Berto nasceu da necessidade de criar uma ponte entre pessoas que precisam de ajuda 
                            e aquelas que têm habilidades para oferecer. Fundada em 2025, nossa plataforma tem 
                            como missão simplificar a vida das pessoas, conectando-as a soluções práticas e 
                            confiáveis para o dia a dia.
                        </p>
                        <p>
                            
                        </p>
                    </div>
                    <div class="story-image">
                        <img 
                            src="https://images.pexels.com/photos/3183153/pexels-photo-3183153.jpeg?auto=compress&cs=tinysrgb&w=600" 
                            alt="Equipe colaborando"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Missão, Visão e Valores -->
        <section class="mission-vision-values">
            <div class="container">
                <h2>Nossos Pilares</h2>
                <div class="pillars">
                    <div class="pillar">
                        <div class="pillar-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3>Missão</h3>
                        <p>
                            Conectar pessoas que precisam de serviços com prestadores qualificados, 
                            criando uma economia colaborativa baseada na confiança e qualidade.
                        </p>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3>Visão</h3>
                        <p>
                            Ser a principal plataforma de serviços colaborativos, reconhecida pela 
                            excelência, inovação e impacto positivo na vida das pessoas.
                        </p>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <h3>Valores</h3>
                        <p>
                            Confiança, transparência, qualidade, inovação e compromisso com a 
                            satisfação de nossa comunidade.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        

        <!-- Como Trabalhamos -->
        <section class="how-we-work">
            <div class="container">
                <h2>Como Trabalhamos</h2>
                <div class="work-process">
                    <div class="process-step">
                        <div class="step-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>Conectamos</h3>
                        <p>Utilizamos tecnologia avançada para conectar você aos melhores prestadores de serviço</p>
                    </div>
                    <div class="process-step">
                        <div class="step-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Verificamos</h3>
                        <p>Todos os prestadores passam por um processo rigoroso de verificação e avaliação</p>
                    </div>
                    <div class="process-step">
                        <div class="step-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Acompanhamos</h3>
                        <p>Oferecemos suporte contínuo durante todo o processo, do primeiro contato à finalização</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Segurança e Confiança -->
        <section class="security-trust">
            <div class="container">
                <div class="security-content">
                    <div class="security-text">
                        <h2>Segurança e Confiança</h2>
                        <div class="security-features">
                            <div class="feature">
                                <i class="fas fa-check-circle"></i>
                                <span>Verificação rigorosa de todos os prestadores</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check-circle"></i>
                                <span>Sistema de avaliações e comentários transparente</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check-circle"></i>
                                <span>Proteção de dados e privacidade garantida</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check-circle"></i>
                                <span>Suporte 24/7 para resolução de problemas</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check-circle"></i>
                                <span>Pagamento seguro e protegido</span>
                            </div>
                        </div>
                    </div>
                    <div class="security-image">
                        <img 
                            src="https://images.pexels.com/photos/4427622/pexels-photo-4427622.jpeg?auto=compress&cs=tinysrgb&w=600" 
                            alt="Segurança digital"
                        />
                    </div>
                </div>
            </div>
        </section>

        

        <!-- CTA -->
        <section class="cta">
            <div class="container">
                <h2>Faça Parte da Nossa Comunidade</h2>
                <p>Junte-se a milhares de pessoas que já confiam em nossa plataforma</p>
                <div class="cta-buttons">
                    <a href="registop2.php" class="btn btn-primary">Criar Conta Grátis</a>
                    <a href="servicos.php" class="btn btn-secondary">Explorar Serviços</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="footer-col">
                    <h4>Empresa</h4>
                    <ul>
                        <li><a href="#">Sobre Nós</a></li>
                        <li><a href="#">Berto © 2025 by Afonso Nunes Ferraz is licensed under CC BY-NC-SA 4.0</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Ajuda</h4>
                    <ul>
                        <li><a href="suporte.php">FAQ</a></li>
                        <li><a href="suporte.php">Como Funciona</a></li>
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

        // Button hover effects
        document.addEventListener('DOMContentLoaded', function() {
            // CTA buttons interactions
            document.querySelectorAll('.cta-buttons .btn').forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px) scale(1.02)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Animate elements on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeIn 0.6s ease-out forwards';
                    }
                });
            }, observerOptions);

            // Observe elements for animation
            document.querySelectorAll('.pillar, .process-step, .stat-card, .feature').forEach(el => {
                observer.observe(el);
            });

            // Counter animation for statistics
            function animateCounter(element, target) {
                let current = 0;
                const increment = target / 100;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    
                    if (target >= 1000) {
                        element.textContent = Math.floor(current / 1000) + 'K+';
                    } else if (target < 10) {
                        element.textContent = current.toFixed(1);
                    } else {
                        element.textContent = Math.floor(current);
                    }
                }, 20);
            }

            // Animate counters when they come into view
            const counterObserver = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const counter = entry.target;
                        const text = counter.textContent;
                        
                        let target;
                        if (text.includes('K+')) {
                            target = parseInt(text.replace('K+', '')) * 1000;
                        } else if (text === '4.8') {
                            target = 4.8;
                        } else if (text === '24/7') {
                            return; // Skip animation for 24/7
                        } else {
                            target = parseInt(text);
                        }
                        
                        if (!isNaN(target)) {
                            animateCounter(counter, target);
                        }
                        
                        counterObserver.unobserve(counter);
                    }
                });
            }, { threshold: 0.5 });

            document.querySelectorAll('.stat-number').forEach(counter => {
                counterObserver.observe(counter);
            });
        });

        // Add loading animation
        window.addEventListener('load', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>