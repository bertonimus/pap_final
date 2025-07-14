<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestao_utilizadores";
if (!isset($_SESSION['id_utilizadores'])) {
    header("Location: resultados_login.php");
    exit();
}
// Get the current user
$nome_usuario = isset($_SESSION["utilizador"]) ? $_SESSION["utilizador"] : "Visitante";


$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Default images for each category
function getDefaultImageForCategory($category)
{
    $defaultImages = [
        'Serviços Digitais' => 'https://images.pexels.com/photos/3861958/pexels-photo-3861958.jpeg?auto=compress&cs=tinysrgb&w=600',
        'Eventos' => 'https://images.pexels.com/photos/1190298/pexels-photo-1190298.jpeg?auto=compress&cs=tinysrgb&w=600',
        'Serviços Domésticos' => 'https://images.pexels.com/photos/4239146/pexels-photo-4239146.jpeg?auto=compress&cs=tinysrgb&w=600',
        'Manutenção' => 'https://images.pexels.com/photos/1249611/pexels-photo-1249611.jpeg?auto=compress&cs=tinysrgb&w=600',
        'Assistência Pessoal' => 'https://images.pexels.com/photos/3760263/pexels-photo-3760263.jpeg?auto=compress&cs=tinysrgb&w=600',
        'Aulas e Treinos' => 'https://images.pexels.com/photos/863988/pexels-photo-863988.jpeg?auto=compress&cs=tinysrgb&w=600',
        'casa' => 'https://images.pexels.com/photos/4239146/pexels-photo-4239146.jpeg?auto=compress&cs=tinysrgb&w=600',
        'digital' => 'https://images.pexels.com/photos/3861958/pexels-photo-3861958.jpeg?auto=compress&cs=tinysrgb&w=600',
        'assistencia' => 'https://images.pexels.com/photos/3760263/pexels-photo-3760263.jpeg?auto=compress&cs=tinysrgb&w=600',
        'manutencao' => 'https://images.pexels.com/photos/1249611/pexels-photo-1249611.jpeg?auto=compress&cs=tinysrgb&w=600',
        'eventos' => 'https://images.pexels.com/photos/1190298/pexels-photo-1190298.jpeg?auto=compress&cs=tinysrgb&w=600',
    ];

    return $defaultImages[$category] ?? 'https://images.pexels.com/photos/1190298/pexels-photo-1190298.jpeg?auto=compress&cs=tinysrgb&w=600';
}

// Check if we're viewing a specific service
$service_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$single_service = null;
if ($service_id) {
    $stmt = $conn->prepare("SELECT s.*, u.utilizador as criador_nome, u.email as criador_email 
                            FROM servicos s
                            LEFT JOIN utilizadores u ON s.id_utilizador = u.id_utilizadores 
                            WHERE s.id_servico = ? And u.id_utilizadores != ?");
    $stmt->bind_param("ii", $service_id, $_SESSION['id_utilizadores']);
    $stmt->execute();
    $result = $stmt->get_result();
    $single_service = $result->fetch_assoc();
    $stmt->close();
} else {
    // Regular services listing - EXCLUDE services with accepted offers that have been paid
    $services = [];
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : 'All';

    // Check if accepted_offers table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'accepted_offers'");
    $accepted_offers_exists = $table_check->num_rows > 0;

    if ($accepted_offers_exists) {
        // New system with accepted_offers table
        $sql = "SELECT s.*, u.utilizador as criador_nome 
                FROM servicos s 
                LEFT JOIN utilizadores u ON s.id_utilizador = u.id_utilizadores 
                WHERE u.id_utilizadores != ". $_SESSION['id_utilizadores']." AND s.id_servico NOT IN (
                    -- Exclude services with accepted offers that have been paid
                    SELECT DISTINCT ao.service_id 
                    FROM accepted_offers ao
                    JOIN ofertas o ON ao.offer_id = o.id
                    WHERE ao.service_id IS NOT NULL 
                    AND o.status IN ('pago_inicial', 'concluida')
                    
                    UNION
                    
                    -- Also exclude services from old system (messages-based)
                    SELECT DISTINCT m.servico_id 
                    FROM mensagens m 
                    JOIN ofertas o ON (
                        (o.remetente_id = m.remetente_id AND o.destinatario_id = m.destinatario_id) OR
                        (o.remetente_id = m.destinatario_id AND o.destinatario_id = m.remetente_id)
                    )
                    WHERE m.servico_id IS NOT NULL 
                    AND o.status IN ('pago_inicial', 'concluida')
                )";
    } else {
        // Fallback to old system
        $sql = "SELECT s.*, u.utilizador as criador_nome 
                FROM servicos s 
                LEFT JOIN utilizadores u ON s.id_utilizador = u.id_utilizadores 
                WHERE s.id_servico NOT IN (
                    SELECT DISTINCT m.servico_id 
                    FROM mensagens m 
                    JOIN ofertas o ON (
                        (o.remetente_id = m.remetente_id AND o.destinatario_id = m.destinatario_id) OR
                        (o.remetente_id = m.destinatario_id AND o.destinatario_id = m.remetente_id)
                    )
                    WHERE m.servico_id IS NOT NULL 
                    AND o.status IN ('pago_inicial', 'concluida')
                )";
    }
    
    $params = [];
    $types = "";

    if (!empty($searchTerm)) {
        $sql .= " AND s.nome LIKE ?";
        $searchParam = "%" . $searchTerm . "%";
        $params[] = &$searchParam;
        $types .= "s";
    }

    if ($category !== 'All' && !empty($category)) {
        $sql .= " AND s.categoria = ?";
        $params[] = &$category;
        $types .= "s";
    }

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $stmt->close();

    // Get categories
    $categories = ['All'];
    $categoryQuery = "SELECT DISTINCT categoria FROM servicos ORDER BY categoria";
    $categoryResult = $conn->query($categoryQuery);

    if ($categoryResult) {
        while ($row = $categoryResult->fetch_assoc()) {
            $categories[] = $row['categoria'];
        }
    }
}

// Buscar nome do usuário
if (isset($_SESSION['id_utilizadores'])) {
    $usuario_id = $_SESSION['id_utilizadores'];
    $stmt = $conn->prepare("SELECT utilizador FROM utilizadores WHERE id_utilizadores = ?");
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
    <title>Berto - Serviços Premium</title>
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
        }

        .page-header h1 {
            font-size: 3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .page-header p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            transition: all var(--transition-speed);
        }

        .back-button:hover {
            background-color: rgba(16, 185, 129, 0.1);
            text-decoration: none;
            color: var(--primary-hover);
        }

        /* Search and Filter */
        .search-filter-section {
            background: var(--card-background);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 3rem;
            border: 1px solid var(--border-color);
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .search-input-group {
            position: relative;
        }

        .search-input-group input {
            width: 100%;
            padding: 1rem 1.5rem 1rem 3rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            transition: all var(--transition-speed);
            background: var(--card-background);
        }

        .search-input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-glow);
        }

        .search-input-group::before {
            content: '\f002';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .category-filters {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .category-btn {
            padding: 0.75rem 1.5rem;
            border: 1px solid var(--border-color);
            background: var(--card-background);
            color: var(--text-secondary);
            border-radius: 25px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-speed);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .category-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            text-decoration: none;
        }

        .category-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .search-btn {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-soft);
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        /* Single Service View */
        .service-detail {
            background: var(--card-background);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .service-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }

        .service-content {
            padding: 3rem;
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 2rem;
        }

        .service-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .service-category {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary-color);
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .service-price {
            text-align: right;
        }

        .service-price .price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .service-price .creator {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .service-description {
            font-size: 1.125rem;
            line-height: 1.8;
            color: var(--text-secondary);
        }

        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .service-card {
            background: var(--card-background);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
            position: relative;
            group: hover;
        }

        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-strong);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .service-card-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform var(--transition-speed);
        }

        .service-card:hover .service-card-image {
            transform: scale(1.05);
        }

        .service-card-content {
            padding: 1.5rem;
        }

        .service-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .service-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .service-card-category {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary-color);
            border-radius: 15px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .service-card-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.125rem;
        }

        .service-card-description {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .service-card-btn {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-align: center;
            transition: all var(--transition-speed);
            display: block;
        }

        .service-card-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        /* Service availability indicator */
        .service-available {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-background);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .no-results i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .no-results h3 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .no-results p {
            color: var(--text-secondary);
            font-size: 1.125rem;
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
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .service-card {
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

            .page-header h1 {
                font-size: 2rem;
            }

            .search-form {
                grid-template-columns: 1fr;
            }

            .category-filters {
                justify-content: center;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .service-header {
                flex-direction: column;
                gap: 1rem;
            }

            .service-title {
                font-size: 2rem;
            }

            .service-content {
                padding: 2rem;
            }
        }

        @media (max-width: 480px) {
            .navbar h1 {
                font-size: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .search-filter-section {
                padding: 1.5rem;
            }

            .service-content {
                padding: 1.5rem;
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
                <li><a href="servicos_resultados.php" class="active">Serviços</a></li>
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
        <?php if ($single_service): ?>
            <!-- Single Service View -->
            <a href="servicos_resultados.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Voltar aos Serviços
            </a>

            <div class="service-detail">
                <img src="<?php echo htmlspecialchars(getDefaultImageForCategory($single_service['categoria'])); ?>" 
                     alt="<?php echo htmlspecialchars($single_service['nome']); ?>" 
                     class="service-image">
                
                <div class="service-content">
                    <div class="service-header">
                        <div>
                            <h1 class="service-title"><?php echo htmlspecialchars($single_service['nome']); ?></h1>
                            <span class="service-category"><?php echo htmlspecialchars($single_service['categoria']); ?></span>
                        </div>
                        <div class="service-price">
                            <div class="price">
                                <?php
                                if (isset($single_service['preco']) && $single_service['preco'] > 0) {
                                    echo '€' . number_format($single_service['preco'], 2);
                                } else {
                                    echo 'Contactar';
                                }
                                ?>
                            </div>
                            <div class="creator">
                                Por <?php echo htmlspecialchars($single_service['criador_nome'] ?? 'Prestador'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="service-description">
                        <?php echo nl2br(htmlspecialchars($single_service['descricao'] ?? 'Descrição não disponível.')); ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Services Listing -->
           

            <!-- Search and Filter -->
            <div class="search-filter-section">
                <form method="GET" class="search-form">
                    <div class="search-input-group">
                        <input type="text" name="search" placeholder="Pesquisar serviços..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search" style="margin-right: 0.5rem;"></i>
                        Buscar
                    </button>
                </form>

                <div class="category-filters">
                    <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?php echo urlencode($cat); ?>&search=<?php echo urlencode($searchTerm); ?>" 
                           class="category-btn <?php echo $category === $cat ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat === 'All' ? 'Todos' : $cat); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Services Grid -->
            <?php if (empty($services)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>Nenhum serviço disponível</h3>
                    <p>Não encontramos serviços disponíveis que correspondam aos seus critérios de pesquisa.</p>
                </div>
            <?php else: ?>
                <div class="services-grid">
                    <?php 
                    $delay = 0;
                    foreach ($services as $service): ?>
                        <div class="service-card" style="animation-delay: <?php echo $delay; ?>ms">
                            <div class="service-available">Disponível</div>
                            <img src="<?php echo htmlspecialchars(getDefaultImageForCategory($service['categoria'])); ?>" 
                                 alt="<?php echo htmlspecialchars($service['nome']); ?>" 
                                 class="service-card-image">
                            
                            <div class="service-card-content">
                                <div class="service-card-header">
                                    <div>
                                        <h3 class="service-card-title"><?php echo htmlspecialchars($service['nome']); ?></h3>
                                        <span class="service-card-category"><?php echo htmlspecialchars($service['categoria']); ?></span>
                                    </div>
                                    <span class="service-card-price">
                                        <?php
                                        if (isset($service['preco']) && $service['preco'] > 0) {
                                            echo '€' . number_format($service['preco'], 2);
                                        } else {
                                            echo 'Contactar';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <p class="service-card-description">
                                    <?php
                                    $description = $service['descricao'] ?? 'Descrição não disponível.';
                                    echo htmlspecialchars(
                                        strlen($description) > 120
                                        ? substr($description, 0, 117) . '...'
                                        : $description
                                    );
                                    ?>
                                </p>
                                
                                <?php if (isset($_SESSION['id_utilizadores'])): ?>
                                    <a href="messages.php?destinatario_id=<?= $service['id_utilizador'] ?>&servico_id=<?= $service['id_servico'] ?>" class="service-card-btn">
                                        <i class="fas fa-comments"></i> Contactar Prestador
                                    </a>
                                <?php else: ?>
                                    <a href="logintexte.php" class="service-card-btn">
                                        <i class="fas fa-sign-in-alt"></i> Fazer Login para Contactar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                        $delay += 100;
                    endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
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
                    <h4>Ajuda</h4>
                    <ul>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Suporte</a></li>
                        <li><a href="#">Contacto</a></li>
                        <li><a href="#">Como Funciona</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Serviços</h4>
                    <ul>
                        <li><a href="#">Desenvolvimento Web</a></li>
                        <li><a href="#">Design UI/UX</a></li>
                        <li><a href="#">Marketing</a></li>
                        <li><a href="#">Consultoria</a></li>
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

        // Search form enhancement
        const searchForm = document.querySelector('.search-form');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                const searchInput = this.querySelector('input[name="search"]');
                if (searchInput.value.trim() === '') {
                    e.preventDefault();
                    showNotification('Por favor, digite o que você está procurando', 'error');
                    searchInput.focus();
                }
            });
        }

        // Service card interactions
        document.querySelectorAll('.service-card-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.href.includes('messages.php')) {
                    const serviceName = this.closest('.service-card').querySelector('.service-card-title').textContent;
                    showNotification(`Abrindo conversa sobre: ${serviceName}`, 'info');
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
```