<?php
$conn = new mysqli("localhost", "root", "", "gestao_utilizadores");

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$error = ""; // Variável para armazenar a mensagem de erro

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = htmlspecialchars($_POST['email']);
    
    // Verificar se o e-mail existe na base de dados
    $result = $conn->query("SELECT * FROM utilizadores WHERE email = '$email'");
    if ($result->num_rows > 0) {
        // Gerar o código de verificação
        $code = random_int(100000, 999999);
        $expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));
        
        // Limpar códigos antigos e inserir o novo
        $conn->query("DELETE FROM password_resets WHERE email = '$email'");
        $conn->query("INSERT INTO password_resets (email, code, expires_at) VALUES ('$email', '$code', '$expires_at')");
        
        // Enviar o código por e-mail
        mail($email, "Código de recuperação", "Seu código de recuperação é: $code");
        
        // Redirecionar para a página de verificação de código
        header("Location: verificar_codigo.php?email=" . urlencode($email));
        exit;
    } else {
        // Configurar mensagem de erro
        $error = "Não há uma conta associada a esse e-mail.";
    }
}
$conn->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha</title>
    <style>
        /* Insira aqui o CSS fornecido */
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
        .error{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <form method="POST" class="form">
            <h2 class="title">Insira o email da conta</h2>
            <div class="input-field">
                <input type="email" name="email" placeholder="Digite seu e-mail" required>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="button">
                <input type="submit" value="Enviar Código">
            </div>
        </form>
    </div>
</body>
</html>