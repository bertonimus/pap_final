<?php
$conn = new mysqli("localhost", "root", "", "gestao_utilizadores");

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$error = ""; // Variável para exibir mensagens de erro ou sucesso

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obter o código enviado pelo formulário e o e-mail passado na URL
    $codigo_digitado = htmlspecialchars($_POST['codigo']);
    $email = htmlspecialchars($_GET['email']);

    // Verificar se o código existe e ainda é válido
    $result = $conn->query("SELECT * FROM password_resets WHERE email = '$email' AND code = '$codigo_digitado' AND expires_at >= NOW()");

    if ($result->num_rows > 0) {
        // Código válido - redirecionar para a página de redefinição de senha
        header("Location: redefinir_senha.php?email=" . urlencode($email));
        exit;
    } else {
        // Código inválido ou expirado
        $error = "Código inválido ou expirado. Tente novamente.";
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código</title>
    <style>
        /* Estilos fornecidos */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #6eda44;
        }

        .container {
            position: relative;
            max-width: 430px;
            width: 100%;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin: 0 20px;
            padding: 30px;
        }

        .form .input-field {
            position: relative;
            height: 50px;
            width: 100%;
            margin-top: 20px;
        }

        .input-field input {
            position: absolute;
            height: 100%;
            width: 100%;
            padding: 0 35px;
            border: none;
            outline: none;
            font-size: 16px;
            border-bottom: 2px solid #ccc;
            border-top: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .form .button {
            margin-top: 20px;
        }

        .form .button input {
            border: none;
            color: #fff;
            font-size: 17px;
            font-weight: 500;
            letter-spacing: 1px;
            border-radius: 6px;
            background-color: #6eda44;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 10px;
        }

        .feedback {
            margin-top: 15px;
            color: #333;
            font-size: 14px;
            text-align: center;
            background-color: #f3f3f3;
            padding: 10px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <form method="POST" class="form">
            <h2 class="title">Verifique o Código</h2>
            
            <div class="input-field">
                <input type="text" name="codigo" placeholder="Digite o código" required>
            </div>
            <div class="button">
                <input type="submit" value="Verificar Código">
            </div>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>