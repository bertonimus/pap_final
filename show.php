<?php
session_start(); // Inicia a sessão

// Configuração da conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = ""; // Insira a senha do usuário root, se houver
$dbname = "ebay"; // Substitua pelo nome do seu banco de dados

// Tenta conectar ao banco de dados e captura qualquer erro de conexão
try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verifica a conexão
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão: " . $conn->connect_error);
    }

    // Recebe os dados do formulário
    $tipo = $_POST['tipo'];
    $nome = $_POST['nome'];
    $sobrenome = isset($_POST['sobrenome']) ? $_POST['sobrenome'] : null;
    $cargo = isset($_POST['cargo']) ? $_POST['cargo'] : null;
    $email = $_POST['email'];
    $data_nascimento = isset($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT); // Criptografa a senha
    $empresa = isset($_POST['empresa']) ? $_POST['empresa'] : null;

    // Insere os dados no banco de dados
    $sql = "INSERT INTO usuarios (tipo, nome, sobrenome, cargo, email, data_nascimento, senha, empresa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro na preparação da declaração: " . $conn->error);
    }
    
    $stmt->bind_param("ssssssss", $tipo, $nome, $sobrenome, $cargo, $email, $data_nascimento, $senha, $empresa);

    if ($stmt->execute()) {
        // Redireciona para a página inicial após o sucesso
        header("Location: pagina_inicial.html");
        exit();
    } else {
        // Verifica se o erro é de e-mail duplicado
        if ($conn->errno === 1062) {
            $_SESSION['error_message'] = "Já existe uma conta com o e-mail informado."; // Armazena a mensagem de erro na sessão
            header("Location: criar_conta.php"); // Redireciona de volta para a página de criação de conta
            exit();
        } else {
            throw new Exception("Erro na execução: " . $stmt->error);
        }
    }

    // Fecha a conexão
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $_SESSION['error_message'] = "Erro: " . $e->getMessage();
    header("Location: registop2.php"); // Redireciona de volta para a página de criação de conta com a mensagem de erro
    exit();
}
?>
