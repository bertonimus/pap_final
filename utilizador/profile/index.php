<?php
session_start();

// Verifica se o utilizador está autenticado
if (!isset($_SESSION['id_utilizadores']) || !isset($_SESSION['id_tipos_utilizador'])) {
    die("Acesso negado. Por favor, faça login para acessar esta página.");
}

// Variáveis da sessão
$id_utilizador = $_SESSION['id_utilizadores']; 
$tipo_utilizador = $_SESSION['id_tipos_utilizador']; 

// Conexão com o banco de dados (substitua os valores conforme necessário)
$servername = "localhost"; // ou o endereço do seu servidor
$username = "root"; // seu usuário do banco de dados
$password = ""; // sua senha do banco de dados
$dbname = "gestao_utilizadores"; // nome do seu banco de dados

$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função de logout
if (isset($_POST['logout'])) {
    header("Location: ../../index.php");
    exit();
}

// Processar a atualização do nome e do e-mail
if (isset($_POST['update_name'])) {
    $new_name = $conn->real_escape_string($_POST['name']);
    $new_email = $conn->real_escape_string($_POST['email']);
    
    // Atualiza o nome e o e-mail no banco de dados
    $sql = "UPDATE utilizadores SET utilizador='$new_name', email='$new_email' WHERE id_utilizadores='$id_utilizador'";
    
    if ($conn->query($sql) === TRUE) {
        $_SESSION['message'] = 'Nome e e-mail atualizados com sucesso!';
    } else {
        $_SESSION['message'] = 'Erro ao atualizar: ' . $conn->error;
    }
}

// Processar a atualização da senha
if (isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verifica se a nova senha e a confirmação correspondem
    if ($new_password !== $confirm_password) {
        $_SESSION['message'] = 'As novas senhas não correspondem.';
    } else {
        // Verifica a senha atual
        $sql = "SELECT password FROM utilizadores WHERE id_utilizadores='$id_utilizador'";
        $result = $conn->query($sql);
        $user = $result->fetch_assoc();

        if ($user && password_verify($current_password, $user['password'])) {
            // Atualiza a senha no banco de dados
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE utilizadores SET password='$hashed_password' WHERE id_utilizadores='$id_utilizador'";
            
            if ($conn->query($sql) === TRUE) {
                $_SESSION['message'] = 'Senha atualizada com sucesso!';
            } else {
                $_SESSION['message'] = 'Erro ao atualizar a senha: ' . $conn->error;
            }
        } else {
            $_SESSION['message'] = 'Senha atual incorreta.';
        }
    }
}

// Buscar informações do usuário
$sql = "SELECT utilizador, email FROM utilizadores WHERE id_utilizadores='$id_utilizador'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

if (!$user) {
    die("Usuário não encontrado.");
}

$nome_utilizador = $user['utilizador'];
$email_utilizador = $user['email'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nome_utilizador); ?> </title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container light-style flex-grow-1 container-p-y">
        <h4 class="font-weight-bold py-3 mb-4">Berto</h4>
        <div class="card overflow-hidden">
            <div class="row no-gutters row-bordered row-border-light">
                <div class="col-md-3 pt-0">
                    <div class="list-group list-group-flush account-settings-links">
                        <a class="list-group-item list-group-item-action active" data-toggle="list" href="#account-general">General</a>
                        <a class="list-group-item list-group-item-action" data-toggle="list" href="#account-change-password">Change password</a>
                      
                        
                        <a class="list-group-item list-group-item-action" data-toggle="list" href="#account-logout">Sair</a>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="account-general">
                            <div class="card-body media align-items-center">
                               
                                
                            </div>
                            <hr class="border-light m-0">
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group">
                                        <label class="form-label">Nome</label>
                                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($nome_utilizador); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" class="form-control mb-1" name="email" value="<?php echo htmlspecialchars($email_utilizador); ?>">
                                    </div>
                                    <div class="text-right mt-3">
                                        <button type="submit" class="btn btn-primary" name="update_name">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="account-change-password">
                            <div class="card-body pb-2">
                                <form method="POST">
                                    <div class="form-group">
                                        <label class="form-label">Current password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">New password</label>
                                        <input type="password" class="form-control" name="new_password" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Repeat new password</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                    <div class="text-right mt-3">
                                        <button type="submit" class="btn btn-primary" name="update_password">Change password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                       
                        <div class="tab-pane fade" id="account-social-links">
                            <div class="card-body pb-2">
                                <div class="form-group">
                                    <label class="form-label">Twitter</label>
                                    <input type="text" class="form-control" value="https://twitter.com/user">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Facebook</label>
                                    <input type="text" class="form-control" value="https://www.facebook.com/user">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Google+</label>
                                    <input type="text" class="form-control" value>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">LinkedIn</label>
                                    <input type="text" class="form-control" value>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Instagram</label>
                                    <input type="text" class="form-control" value="https://www.instagram.com/user">
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="account-logout">
                            <div class="card-body">
                                <h5>Você tem certeza que deseja sair?</h5>
                                <form method="POST">
                                    <button type="submit" class="btn btn-danger" name="logout">Sair</button>
                                    <button type="button" class="btn btn-default" onclick="cancelLogout()">Cancelar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-right mt-3">
            <button type="button" class="btn btn-primary">Save changes</button>&nbsp;
            <button type="button" class="btn btn-default">Cancel</button>
        </div>
    </div>

    <?php
    // Exibir mensagem de sucesso ou erro
    if (isset($_SESSION['message'])) {
        echo "<script>alert('" . $_SESSION['message'] . "');</script>";
        unset($_SESSION['message']); // Limpa a mensagem após exibi-la
    }
    ?>

    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="https://code.jquery.com/jquery-1.10.2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript">
        function cancelLogout() {
            $('.nav-tabs a[href="#account-notifications"]').tab('show');
        }
    </script>
</body>
        
</html>