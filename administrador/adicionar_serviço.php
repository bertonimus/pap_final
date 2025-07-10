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

// Definição das categorias permitidas
$categorias_permitidas = [
    "Serviços Domésticos",
    "Serviços Digitais",
    "Assistência Pessoal",
    "Manutenção",
    "Eventos",
    "Aulas e Treinos
"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recebe os dados do formulário
    $nome = $conn->real_escape_string($_POST['nome']);
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $preco = (float)$_POST['preco'];
    $categoria = $conn->real_escape_string($_POST['categoria']);

    // Verifica se a categoria é válida
    if (!in_array($categoria, $categorias_permitidas)) {
        die("Categoria inválida. Escolha uma das opções disponíveis.");
    }

    // Lida com o upload de imagem (opcional)
    $imagem = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
        $imagemNome = uniqid() . "-" . basename($_FILES['imagem']['name']);
        $imagemDestino = "../utilizador/uploads/" . $imagemNome;

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $imagemDestino)) {
            $imagem = $imagemNome;
        } else {
            echo "Erro ao fazer upload da imagem.";
            exit;
        }
    }

    // Query de inserção (incluindo o id_utilizador)
    $sql = "INSERT INTO servicos (nome, descricao, preco, imagem, categoria, id_utilizador)
            VALUES ('$nome', '$descricao', '$preco', '$imagem', '$categoria', '$id_utilizador')";

    if ($conn->query($sql) === TRUE) {
        echo "Serviço adicionado com sucesso!";
        header("Location: gestao_serviços.php");
        exit;
    } else {
        echo "Erro ao adicionar o serviço: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Serviço</title>
    <link rel="stylesheet" href="../styles/gestao.css">
    <style>
        .add-service-form input,
        .add-service-form textarea,
        .add-service-form select,
        .add-service-form button {
            margin-bottom: 20px; /* Espaço entre as caixas de texto */
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const form = document.querySelector('.add-service-form');
            form.addEventListener('submit', (e) => {
                const nome = document.querySelector('input[name="nome"]').value;
                const preco = document.querySelector('input[name="preco"]').value;
                
                if (nome.trim() === '' || preco.trim() === '') {
                    alert('Os campos Nome do Serviço e Preço são obrigatórios.');
                    e.preventDefault();
                }
            });
        });
    </script>
</head>
<body>
<div class="sidebar">
    <h2>Menu</h2>
    <button onclick="window.location.href='adicionar_serviço.php'">Adicionar Serviços</button>
    <button onclick="window.location.href='gestao_serviços.php'">Gerir Serviços</button>
    <button onclick="window.location.href='editar_utilizadores.php'">Voltar</button>
</div>

<div class="content">
    <h3>Adicionar Novo Serviço</h3>
    <form action="adicionar_serviço.php" method="POST" enctype="multipart/form-data" class="add-product-form">
        <input type="text" name="nome" placeholder="Nome do Serviço" required>
        <textarea name="descricao" placeholder="Descrição do Serviço"></textarea>
        <input type="number" name="preco" step="0.01" placeholder="Preço (€)" required>
        <input type="number" name="vagas" placeholder="Quantidade de vagas" required>
        
        <!-- Select para as categorias -->
        <select name="categoria" required>
            <option value="" disabled selected>Selecione uma Categoria</option>
            <?php foreach ($categorias_permitidas as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="file" name="imagem" accept="image/*">
        <button type="submit" class="btn insert">Adicionar Serviço</button>
    </form>
</div>
</body>
</html>
