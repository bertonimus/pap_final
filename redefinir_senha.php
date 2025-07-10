<?php
$conn = new mysqli("localhost", "root", "", "gestao_utilizadores");

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obter os valores enviados pelo formulário
    $email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : null;
    $new_password = isset($_POST['password']) ? htmlspecialchars($_POST['password']) : null;

    // Verificar se os campos estão preenchidos
    if ($email && $new_password) {
        // Atualizar a senha no banco de dados usando PASSWORD()
        $stmt = $conn->prepare("UPDATE utilizadores SET password = PASSWORD(?) WHERE email = ?");
        $stmt->bind_param("ss", $new_password, $email);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Redirecionar para a página de confirmação de senha alterada
            header("Location: senha_alterada.php");
            exit();
        } else {
            echo "Erro ao redefinir a senha ou e-mail não encontrado.";
        }
        $stmt->close();
    } else {
        echo "Por favor, preencha todos os campos.";
    }
}

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha</title>

</head>
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
    </style>

<body>
    <div class="container">
        <form method="POST" class="form">
            <h2 class="title">Redefinir Password</h2>
            <div class="input-field">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                <input type="password" name="password" placeholder="Nova password" required>
            </div>
            <div class="button">
                <input type="submit" value="Redefinir Senha">
            </div>
            <?php if (!empty($message)): ?>
                <div class="message <?php echo strpos($message, 'sucesso') !== false ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
</body>

</html>