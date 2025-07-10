<?php
session_start();

// Verifica se o utilizador está autenticado
if (!isset($_SESSION['id_utilizadores']) || !isset($_SESSION['id_tipos_utilizador'])) {
    die("Acesso negado. Por favor, faça login para acessar esta página.");
}

// Variáveis da sessão
$id_utilizador = $_SESSION['id_utilizadores']; // ID do utilizador logado
$tipo_utilizador = $_SESSION['id_tipos_utilizador']; // Tipo geral do utilizador
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Produtos</title>
    <link rel="stylesheet" href="../styles/gestao.css">
    <style>
        .edit-form-container {
            display: none; /* Inicialmente oculto */
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 80%; /* Reduzido para ocupar menos espaço */
            max-width: 600px; /* Limita a largura máxima */
            margin-left: auto;
            margin-right: auto;
            font-family: Arial, sans-serif;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .edit-form-container h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .edit-form-container label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-top: 5px;
        }

        .edit-form-container input, 
        .edit-form-container select, 
        .edit-form-container textarea {
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            width: 100%; /* Usando 100% de largura para os campos */
            box-sizing: border-box;
        }

        .edit-form-container textarea {
            min-height: 60px; /* Definindo altura mínima para a área de texto */
        }

        .form-row {
            display: flex;
            flex-direction: column; /* Coloca os campos em coluna */
            gap: 10px; /* Ajuste do espaçamento entre os campos */
        }

        .form-buttons {
            text-align: center;
            margin-top: 10px;
        }

        .btn-save {
            background-color: #28a745;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            width: 45%; /* Botão ocupa menos espaço */
        }

        .btn-cancel {
            background-color: #dc3545;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            width: 45%; /* Botão ocupa menos espaço */
            margin-left: 10px;
        }

        .btn-save:hover, 
        .btn-cancel:hover {
            opacity: 0.9;
        }

        .btn.delete {
            background-color: #f44336; /* Vermelho */
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 3px;
        }

        .btn.delete:hover {
            background-color: #d32f2f;
        }
    </style>
    <script>
        function toggleEditForm(produtoId) {
            const formContainer = document.getElementById(`edit-form-${produtoId}`);
            if (formContainer.style.display === "none" || formContainer.style.display === "") {
                formContainer.style.display = "block";
            } else {
                formContainer.style.display = "none";
            }
        }
    </script>
</head>
<body>
<div class="sidebar">
    <h2>Berto</h2>
    <button onclick="window.location.href='adicionar_produto.php'">Adicionar produto</button>
        <button onclick="window.location.href='gestao_produto.php'">Gerir Produtos</button>
        <button onclick="window.location.href='gestao_produtos.php'">Voltar</button>
</div>

<?php
// Conexão com o banco de dados
$conn = new mysqli("localhost", "root", "", "gestao_utilizadores");

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Verifica se o formulário de edição foi submetido
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['editar_produto'])) {
    $produtoId = (int)$_POST['produto_id'];
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $preco = (float)$_POST['preco'];
    $quantidade = (int)$_POST['quantidade'];
    $categoria = $_POST['categoria'];

    // Atualiza o produto no banco de dados
    $sqlAtualizar = "UPDATE produtos SET 
                        nome = '$nome', 
                        descricao = '$descricao', 
                        preco = $preco, 
                        quantidade = $quantidade, 
                        categoria = '$categoria'";

    // Verifica se uma nova imagem foi enviada
    if (!empty($_FILES['imagem']['name'])) {
        $imagem = $_FILES['imagem']['name'];
        $caminhoImagem = "uploads/" . basename($imagem);
        move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoImagem);
        $sqlAtualizar .= ", imagem = '$imagem'";
    }

    $sqlAtualizar .= " WHERE id = $produtoId";

    if ($conn->query($sqlAtualizar) === TRUE) {
        echo "<p>Produto atualizado com sucesso!</p>";
    } else {
        echo "<p>Erro ao atualizar produto: " . $conn->error . "</p>";
    }
}

// Verifica se o formulário de remoção foi submetido
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remover_produto'])) {
    $produtoId = (int)$_POST['remover_produto'];

    // Obtém o caminho da imagem para excluir
    $sqlImagem = "SELECT imagem FROM produtos WHERE id = $produtoId";
    $resultImagem = $conn->query($sqlImagem);
    if ($resultImagem->num_rows > 0) {
        $imagem = $resultImagem->fetch_assoc()['imagem'];
        $caminhoImagem = "uploads/" . $imagem;

        // Remove a imagem do servidor, se existir
        if (file_exists($caminhoImagem)) {
            unlink($caminhoImagem);
        }
    }

    // Remove o produto do banco de dados
    $sqlRemover = "DELETE FROM produtos WHERE id = $produtoId";
    if ($conn->query($sqlRemover) === TRUE) {
        echo "<p>Produto removido com sucesso!</p>";
    } else {
        echo "<p>Erro ao remover produto: " . $conn->error . "</p>";
    }
}

// Query para buscar os produtos
if ($id_utilizador == 0) {
    $sql = "SELECT * FROM produtos";
} else {
    $sql = "SELECT * FROM produtos WHERE id_utilizador = $id_utilizador";
}

$result = $conn->query($sql);

// Exibir tabela de produtos
echo "<h3>Lista de Produtos</h3>";
echo "<table class='product-table'>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Preço</th>
                <th>Quantidade</th>
                <th>Categoria</th>
                <th>Imagem</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['nome']}</td>
                <td>{$row['descricao']}</td>
                <td>{$row['preco']}</td>
                <td>{$row['quantidade']}</td>
                <td>{$row['categoria']}</td>
                <td><img src='uploads/{$row['imagem']}' alt='Imagem' width='50'></td>
                <td>
                    <button type='button' class='btn edit' onclick='toggleEditForm({$row['id']})'>Editar</button>
                    <form action='' method='POST' style='display: inline;'>
                        <input type='hidden' name='remover_produto' value='{$row['id']}'>
                        <button type='submit' class='btn delete'>Remover</button>
                    </form>
                </td>
              </tr>";
        
        // Formulário de edição
        echo "<tr id='edit-form-{$row['id']}' class='edit-form-container'>
                <td colspan='8'>
                    <form action='' method='POST' enctype='multipart/form-data'>
                        <h3>Editar Produto</h3>
                        <input type='hidden' name='produto_id' value='{$row['id']}'>
                        <div class='form-row'>
                            <label>Nome:</label>
                            <input type='text' name='nome' value='{$row['nome']}' required>

                            <label>Preço:</label>
                            <input type='number' name='preco' step='0.01' value='{$row['preco']}' required>
                        </div>
                        <div class='form-row'>
                            <label>Descrição:</label>
                            <textarea name='descricao' required>{$row['descricao']}</textarea>
                        </div>
                        <div class='form-row'>
                            <label>Quantidade:</label>
                            <input type='number' name='quantidade' value='{$row['quantidade']}' required>
                        </div>
                        <div class='form-row'>
                            <label>Categoria:</label>
                            <select name='categoria'>
                                <option value='{$row['categoria']}'>{$row['categoria']}</option>
                                <option value='Eletrônicos'>Eletrônicos</option>
                                <option value='Roupas'>Roupas</option>
                                <option value='Móveis'>Móveis</option>
                            </select>
                        </div>
                        <div class='form-row'>
                            <label>Imagem:</label>
                            <input type='file' name='imagem'>
                        </div>
                        <div class='form-buttons'>
                            <button type='submit' name='editar_produto' class='btn-save'>Salvar</button>
                            <button type='button' class='btn-cancel' onclick='toggleEditForm({$row['id']})'>Cancelar</button>
                        </div>
                    </form>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7'>Nenhum produto encontrado.</td></tr>";
}

echo "</tbody></table>";

$conn->close();
?>
</body>
</html>
