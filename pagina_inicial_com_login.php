<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestao_utilizadores";

// Get the current user
$nome_usuario = isset($_SESSION["utilizador"]) ? $_SESSION["utilizador"] : "Visitante";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "An error occurred while connecting to the database.";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exemplo de Navegação</title>
    
    <link rel="stylesheet" href="styles/footer.css">
    <link rel="stylesheet" href="styles/livechat.css">
    <link rel="stylesheet" href="styles/header2.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
</head>
<style>
    .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: #f8f9fa;
        }

        .navbar-list {
            list-style: none;
            display: flex;
            gap: 25px;
            margin-left: 40px;
            padding: 0;
        }

        .navbar-list a {
            text-decoration: none;
            color: #333;
            font-weight: bold;
        }

        .navbar-list a.active {
            color: #28a745;
        }
</style>
<body>
       <nav class="navbar">
        <h1>Berto</h1>
        <ul class="navbar-list">
            <li><a href="pagina_inicial_com_login" class="active">inicio</a></li>
            <li><a href="produtos.php">produtos</a></li>
            <li><a href="serviços_login.php" >serviços</a></li>
            <li><a href="suporte.php">Suporte</a></li>
            <li><a href="messages.php">Mensagens</a></li>
            <li><a href="#">sobre</a></li>
        </ul>

        <div class="profile-dropdown">
            <div onclick="toggle()" class="profile-dropdown-btn">
                <div class="profile-img">
                    <i class="fa-solid fa-circle"></i>
                </div>
                <span>
                    <?php echo htmlspecialchars($nome_usuario); ?>
                    <i class="fa-solid fa-angle-down"></i>
                </span>
            </div>

            <ul class="profile-dropdown-list">
            <li class="profile-dropdown-list-item">
                    <a href="">
                        <i class="fa-regular fa-user"></i>
                        Editar Perfil
                    </a>
                </li>
                <li class="profile-dropdown-list-item">
                    <a href="utilizador/profile/index.php">
                        <i class="fa-solid fa-sliders"></i>
                        Settings
                    </a>
                </li>
                <li class="profile-dropdown-list-item">
                    <a href="utilizador/gestao_produtos.php">
                        <i class="fa-regular fa-circle-question"></i>
                        Gestão de produtos
                    </a>
                </li>
                <hr/>
                <li class="profile-dropdown-list-item">
                    <form id="logout-form" action="utilizador/logout.php" method="POST">
                        <input type="hidden" name="botaoLogout">
                        <a href="#" onclick="document.getElementById('logout-form').submit();">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            Log out
                        </a>
                    </form>
                </li>
            </ul>
        </div>
    </nav>

   

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="footer-col">
                    <h4>company</h4>
                    <ul>
                        <li><a href="#">about us</a></li>
                        <li><a href="#">our services</a></li>
                        <li><a href="#">privacy policy</a></li>
                        <li><a href="#">affiliate program</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>get help</h4>
                    <ul>
                        <li><a href="#">faq</a></li>
                        <li><a href="#">shipping</a></li>
                        <li><a href="#">returns</a></li>
                        <li><a href="#">order status</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>follow us</h4>
                    <ul>
                        <li><a href="#">facebook</a></li>
                        <li><a href="#">twitter</a></li>
                        <li><a href="#">instagram</a></li>
                        <li><a href="#">youtube</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <script src="scripts/header.js"></script>
    <script src="chat/chat.js"></script>
</body>
</html>