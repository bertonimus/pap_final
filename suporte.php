<?php
session_start();

// Verifica se o utilizador está logado com base na sessão
$estaLogado = isset($_SESSION['email']);
$utilizador = $_SESSION['utilizador'] ?? '';
$nome_usuario = isset($_SESSION["utilizador"]) ? $_SESSION["utilizador"] : "Visitante";

// Verificar mensagens de sucesso ou erro
$success_message = '';
$error_messages = [];
$form_data = [];

if (isset($_SESSION['support_success'])) {
    $success_message = $_SESSION['support_success'];
    unset($_SESSION['support_success']);
}

if (isset($_SESSION['support_errors'])) {
    $error_messages = $_SESSION['support_errors'];
    unset($_SESSION['support_errors']);
}

if (isset($_SESSION['support_form_data'])) {
    $form_data = $_SESSION['support_form_data'];
    unset($_SESSION['support_form_data']);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Centro de Suporte</title>
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

        .alert i {
            font-size: 1.25rem;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        /* Hero Section */
        .hero-section {
            text-align: center;
            margin-bottom: 4rem;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(59, 130, 246, 0.1));
            border-radius: var(--border-radius-lg);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.05) 0%, transparent 70%);
            animation: float 20s ease-in-out infinite;
        }

        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .hero-section p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            z-index: 1;
        }

        /* Support Options Grid */
        .support-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .support-card {
            background: var(--card-background);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .support-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.1), transparent);
            transition: left 0.6s;
        }

        .support-card:hover::before {
            left: 100%;
        }

        .support-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-strong);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .support-card i {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .support-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .support-card p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .support-card .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-soft);
        }

        .support-card .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            text-decoration: none;
            color: white;
        }

        /* Contact Form Section */
        .contact-section {
            background: var(--card-background);
            padding: 3rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            margin-bottom: 4rem;
        }

        .contact-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-align: center;
        }

        .contact-section .subtitle {
            font-size: 1.125rem;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 3rem;
        }

        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            transition: all var(--transition-speed);
            background: var(--card-background);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-glow);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .priority-select {
            position: relative;
        }

        .priority-select select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-soft);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* FAQ Section */
        .faq-section {
            margin-bottom: 4rem;
        }

        .faq-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-align: center;
        }

        .faq-section .subtitle {
            font-size: 1.125rem;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 3rem;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .faq-item {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: all var(--transition-speed);
        }

        .faq-item:hover {
            box-shadow: var(--shadow-soft);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .faq-question {
            padding: 1.5rem;
            background: var(--card-background);
            border: none;
            width: 100%;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all var(--transition-speed);
        }

        .faq-question:hover {
            background: rgba(16, 185, 129, 0.05);
        }

        .faq-question i {
            transition: transform var(--transition-speed);
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: all var(--transition-speed);
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .faq-item.active .faq-answer {
            padding: 0 1.5rem 1.5rem;
            max-height: 200px;
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
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .support-card,
        .faq-item {
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

            .hero-section {
                padding: 3rem 1rem;
            }

            .hero-section h1 {
                font-size: 2.5rem;
            }

            .contact-section {
                padding: 2rem;
            }

            .faq-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .hero-section h1 {
                font-size: 2rem;
            }

            .navbar h1 {
                font-size: 1.5rem;
            }

            .support-options {
                grid-template-columns: 1fr;
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
            <li><a href="serviços_login.php">Serviços</a></li>
            <li><a href="suporte.php" class="active">Suporte</a></li>
            <li><a href="messages.php">Mensagens</a></li>
            <li><a href="sobre.php" >Sobre</a></li>
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
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_messages)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <?php foreach ($error_messages as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="hero-section">
            <h1>Centro de Suporte</h1>
            <p>Estamos aqui para ajudar! Encontre respostas rápidas ou entre em contato conosco diretamente</p>
        </section>

        <!-- Support Options -->
        <section class="support-options">
            <div class="support-card" style="animation-delay: 0ms">
                <i class="fas fa-comments"></i>
                <h3>Chat ao Vivo</h3>
                <p>Converse com nossa equipe de suporte em tempo real para obter ajuda imediata</p>
                <a href="#" class="btn" onclick="showNotification('Chat ao vivo será implementado em breve!', 'info')">Iniciar Chat</a>
            </div>
            <div class="support-card" style="animation-delay: 100ms">
                <i class="fas fa-envelope"></i>
                <h3>Enviar Ticket</h3>
                <p>Descreva seu problema detalhadamente e nossa equipe responderá em até 24 horas</p>
                <a href="#contact-form" class="btn">Criar Ticket</a>
            </div>
            <div class="support-card" style="animation-delay: 200ms">
                <i class="fas fa-phone"></i>
                <h3>Suporte Telefônico</h3>
                <p>Ligue para nossa central de atendimento de segunda a sexta, das 9h às 18h</p>
                <a href="tel:+351123456789" class="btn">+351 123 456 789</a>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="faq-section">
            <h2>Perguntas Frequentes</h2>
            <p class="subtitle">Encontre respostas rápidas para as dúvidas mais comuns</p>
            
            <div class="faq-grid">
                <div class="faq-item" style="animation-delay: 0ms">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        Como posso criar uma conta?
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Para criar uma conta, clique em "Registrar" no menu superior e preencha o formulário com suas informações pessoais.
                    </div>
                </div>

                <div class="faq-item" style="animation-delay: 100ms">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        Como posso redefinir minha senha?
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Na página de login, clique em "Esqueci minha senha" e digite seu email. Você receberá um link para redefinir sua senha em alguns minutos.
                    </div>
                </div>

                

            </div>
        </section>

        <!-- Contact Form -->
        <section class="contact-section" id="contact-form">
            <h2>Formulário de Suporte</h2>
            <p class="subtitle">Não encontrou a resposta que procurava? Entre em contato conosco</p>
            
            <div class="form-container">
                <form action="processar_suporte.php" method="POST" onsubmit="return validateForm(this)">
                    <div class="form-group">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($form_data['nome'] ?? $utilizador) ?>" required>
                    </div>

                    <?php if (!$estaLogado): ?>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" placeholder="seu@email.com" required>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="categoria">Categoria do Problema</label>
                        <div class="priority-select">
                            <select id="categoria" name="categoria" required>
                                <option value="">Selecione uma categoria</option>
                                <option value="conta" <?= ($form_data['categoria'] ?? '') === 'conta' ? 'selected' : '' ?>>Problemas com Conta</option>
                                <option value="pagamento" <?= ($form_data['categoria'] ?? '') === 'pagamento' ? 'selected' : '' ?>>Pagamentos e Faturação</option>
                                <option value="pedido" <?= ($form_data['categoria'] ?? '') === 'pedido' ? 'selected' : '' ?>>Pedidos e Entregas</option>
                                <option value="produto" <?= ($form_data['categoria'] ?? '') === 'produto' ? 'selected' : '' ?>>Problemas com Produtos</option>
                                <option value="tecnico" <?= ($form_data['categoria'] ?? '') === 'tecnico' ? 'selected' : '' ?>>Suporte Técnico</option>
                                <option value="outro" <?= ($form_data['categoria'] ?? '') === 'outro' ? 'selected' : '' ?>>Outro</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="prioridade">Prioridade</label>
                        <div class="priority-select">
                            <select id="prioridade" name="prioridade" required>
                                <option value="">Selecione a prioridade</option>
                                <option value="baixa" <?= ($form_data['prioridade'] ?? '') === 'baixa' ? 'selected' : '' ?>>Baixa - Dúvida geral</option>
                                <option value="media" <?= ($form_data['prioridade'] ?? '') === 'media' ? 'selected' : '' ?>>Média - Problema que afeta o uso</option>
                                <option value="alta" <?= ($form_data['prioridade'] ?? '') === 'alta' ? 'selected' : '' ?>>Alta - Problema urgente</option>
                                <option value="critica" <?= ($form_data['prioridade'] ?? '') === 'critica' ? 'selected' : '' ?>>Crítica - Sistema não funciona</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="assunto">Assunto</label>
                        <input type="text" id="assunto" name="assunto" value="<?= htmlspecialchars($form_data['assunto'] ?? '') ?>" placeholder="Descreva brevemente o problema" required>
                    </div>

                    <div class="form-group">
                        <label for="mensagem">Descrição Detalhada</label>
                        <textarea id="mensagem" name="mensagem" placeholder="Descreva seu problema ou dúvida em detalhes. Inclua informações como quando o problema ocorreu, que ações você estava realizando, mensagens de erro, etc." required><?= htmlspecialchars($form_data['mensagem'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane" style="margin-right: 0.5rem;"></i>
                        Enviar Solicitação
                    </button>
                </form>
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
                        <li><a href="#">Nossos Serviços</a></li>
                        <li><a href="#">Política de Privacidade</a></li>
                        <li><a href="#">Programa de Afiliados</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="#contact-form">Centro de Suporte</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Contato</a></li>
                        <li><a href="#">Status do Sistema</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Recursos</h4>
                    <ul>
                        <li><a href="#">Documentação</a></li>
                        <li><a href="#">Tutoriais</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Comunidade</a></li>
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

        // FAQ Toggle
        function toggleFAQ(button) {
            const faqItem = button.closest('.faq-item');
            const isActive = faqItem.classList.contains('active');
            
            // Close all FAQ items
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Open clicked item if it wasn't active
            if (!isActive) {
                faqItem.classList.add('active');
            }
        }

        // Form validation
        function validateForm(form) {
            const nome = form.nome.value.trim();
            const assunto = form.assunto.value.trim();
            const mensagem = form.mensagem.value.trim();
            const categoria = form.categoria.value;
            const prioridade = form.prioridade.value;

            if (!nome || nome.length < 2) {
                showNotification('Por favor, insira um nome válido', 'error');
                return false;
            }

            if (!categoria) {
                showNotification('Por favor, selecione uma categoria', 'error');
                return false;
            }

            if (!prioridade) {
                showNotification('Por favor, selecione a prioridade', 'error');
                return false;
            }

            if (!assunto || assunto.length < 5) {
                showNotification('Por favor, insira um assunto mais descritivo', 'error');
                return false;
            }

            if (!mensagem || mensagem.length < 20) {
                showNotification('Por favor, forneça uma descrição mais detalhada (mínimo 20 caracteres)', 'error');
                return false;
            }

            showNotification('Enviando sua solicitação...', 'info');
            return true;
        }

        // Smooth scroll for anchor links
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

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: ${type === 'success' ? 'var(--success-color)' : type === 'error' ? 'var(--error-color)' : 'var(--accent-blue)'};
                color: white;
                border-radius: 12px;
                box-shadow: var(--shadow-strong);
                z-index: 10000;
                font-weight: 500;
                animation: slideIn 0.3s ease-out;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                max-width: 400px;
            `;
            
            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            notification.innerHTML = `<i class="fas ${icon}"></i>${message}`;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
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

        // Auto-resize textarea
        document.getElementById('mensagem').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.max(120, this.scrollHeight) + 'px';
        });

        // Form field enhancements
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('focus', function() {
                this.closest('.form-group').style.transform = 'scale(1.02)';
            });
            
            field.addEventListener('blur', function() {
                this.closest('.form-group').style.transform = 'scale(1)';
            });
        });

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>