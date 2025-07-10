
<?php
session_start();

// Verifica se o utilizador está autenticado
if (!isset($_SESSION['id_utilizadores'])) {
    die("Acesso negado. Por favor, faça login para acessar esta página.");
}

// Captura o ID do utilizador logado
$id_utilizador = $_SESSION['id_utilizadores'];

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recebe os dados do formulário
    $nome = $conn->real_escape_string($_POST['nome']);
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $preco = (float)$_POST['preco'];
    $quantidade = (int)$_POST['quantidade'];
    $categoria = $conn->real_escape_string($_POST['categoria']);

    // Lida com o upload de imagem
    $imagem = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
        $imagemNome = uniqid() . "-" . basename($_FILES['imagem']['name']);
        $imagemDestino = "uploads/" . $imagemNome;

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $imagemDestino)) {
            $imagem = $imagemNome;
        } else {
            echo "Erro ao fazer upload da imagem.";
            exit;
        }
    }

    // Query de inserção (incluindo o id_utilizador)
    $sql = "INSERT INTO produtos (nome, descricao, preco, quantidade, categoria, imagem, id_utilizador)
            VALUES ('$nome', '$descricao', '$preco', '$quantidade', '$categoria', '$imagem', '$id_utilizador')";

    if ($conn->query($sql) === TRUE) {
        echo "Produto adicionado com sucesso!";
        header("Location: gestao_produtos.php");
        exit;
    } else {
        echo "Erro ao adicionar o produto: " . $conn->error;
    }
}

$conn->close();
?>

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../styles/gestao.css"> 
</head>
<body>


<div class="sidebar">
        <h2>Berto</h2>
        <button onclick="window.location.href='adicionar_produto.php'">Adicionar produto</button>
        <button onclick="window.location.href='gestao_produto.php'">Gerir Produtos</button>
        <button onclick="window.location.href='gestao_produtos.php'">Voltar</button>
        

</div>

<div class="content">
    <h3>Adicionar Novo Produto</h3>
    <form action="adicionar_produto.php" method="POST" enctype="multipart/form-data" class="add-product-form">
        <input type="text" name="nome" placeholder="Nome do Produto" required>
        <textarea name="descricao" placeholder="Descrição do Produto"></textarea>
        <input type="number" name="preco" step="0.01" placeholder="Preço (€)" required>
        <input type="number" name="quantidade" placeholder="Quantidade em Estoque" required>
        <input type="text" name="categoria" placeholder="Categoria do Produto">
        <input type="file" name="imagem" accept="image/*">
        <button type="submit" class="btn insert">Adicionar Produto</button>
    </form>
</div>
</body>
</html>