    <?php
    session_start();

    // Verifica se o utilizador está autenticado
    if (!isset($_SESSION['id_utilizadores']) || !isset($_SESSION['id_tipos_utilizador'])) {
        die("Acesso negado. Por favor, faça login para acessar esta página.");
    }

    // Variáveis da sessão
    $id_utilizador = $_SESSION['id_tipos_utilizador']; 
    $tipo_utilizador = $_SESSION['id_tipos_utilizador']; 
    ?>

    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestão de Serviços</title>
        <link rel="stylesheet" href="../styles/gestao.css">
        <style>
            /* Estilos existentes */
            .edit-form-container {
                display: none;
                margin-top: 20px;
                padding: 10px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 5px;
                width: 80%;
                max-width: 600px;
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
                width: 100%;
                box-sizing: border-box;
            }

            .edit-form-container textarea {
                min-height: 60px;
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
                width: 45%;
            }

            .btn-cancel {
                background-color: #dc3545;
                color: #fff;
                padding: 10px 15px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                width: 45%;
                margin-left: 10px;
            }

            .btn-save:hover, .btn-cancel:hover {
                opacity: 0.9;
            }

            .btn.delete {
                background-color: #f44336;
                color: white;
                border: none;
                padding: 5px 10px;
                cursor: pointer;
                border-radius: 3px;
            }

            .btn.delete:hover {
                background-color: #d32f2f;
            }

            .pagination {
                margin-top: 20px;
                text-align: center;
            }

            .pagination a {
                margin: 0 5px;
                text-decoration: none;
                color: #007bff;
            }

            .pagination strong {
                margin: 0 5px;
                font-weight: bold;
            }
        </style>
        <script>
            function toggleEditForm(servicoId) {
                const formContainer = document.getElementById(`edit-form-${servicoId}`);
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
        <h2>Menu</h2>
        <button onclick="window.location.href='adicionar_serviço.php'">Adicionar Serviços</button>
        <button onclick="window.location.href='gestao_serviços.php'">Gerir Serviços</button>
        <button onclick="window.location.href='editar_utilizadores.php'">Voltar</button>
    </div>

    <?php
    $conn = new mysqli("localhost", "root", "", "gestao_utilizadores");
    if ($conn->connect_error) {
        die("Conexão falhou: " . $conn->connect_error);
    }

    // Remover serviço
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remover_servico'])) {
        $servicoId = (int)$_POST['remover_servico'];
        $sqlRemover = "DELETE FROM servicos WHERE id_servico = $servicoId";
        if ($conn->query($sqlRemover) === TRUE) {
            echo "<p>Serviço removido com sucesso!</p>";
        } else {
            echo "<p>Erro ao remover serviço: " . $conn->error . "</p>";
        }
    }

    // Paginação
    $servicosPorPagina = 5; // Número de serviços por página
    $paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $offset = ($paginaAtual - 1) * $servicosPorPagina;

    // Buscar serviços
    $sql = $id_utilizador == 0 ? "SELECT * FROM servicos LIMIT $servicosPorPagina OFFSET $offset" : "SELECT * FROM servicos WHERE id_utilizador = $id_utilizador LIMIT $servicosPorPagina OFFSET $offset";
    $result = $conn->query($sql);

    // Obtenha o total de serviços para calcular o número de páginas
    $totalServicosSql = $id_utilizador == 0 ? "SELECT COUNT(*) as total FROM servicos" : "SELECT COUNT(*) as total FROM servicos WHERE id_utilizador = $id_utilizador";
    $totalResult = $conn->query($totalServicosSql);
    $totalRow = $totalResult->fetch_assoc ();
    $totalServicos = $totalRow['total'];
    $totalPaginas = ceil($totalServicos / $servicosPorPagina);

    // Exibir tabela
    echo "<h3>Lista de Serviços</h3>";
    echo "<table class='service-table'><thead><tr><th>Nome</th><th>Descrição</th><th>Preço</th><th>Categoria</th><th>Ações</th></tr></thead><tbody>";

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['nome']}</td><td>{$row['descricao']}</td><td>{$row['preco']}</td><td>{$row['categoria']}</td><td>
                <button class='btn edit' onclick='toggleEditForm({$row['id_servico']})'>Editar</button>
                <form action='' method='POST' style='display: inline;'>
                    <input type='hidden' name='remover_servico' value='{$row['id_servico']}'>
                    <button type='submit' class='btn delete'>Remover</button>
                </form>
            </td></tr>";
            echo "<tr id='edit-form-{$row['id_servico']}' class='edit-form-container'><td colspan='5'>
                <form action='' method='POST'>
                    <h3>Editar Serviço</h3>
                    <input type='hidden' name='servico_id' value='{$row['id_servico']}'>
                    <label>Nome:</label>
                    <input type='text' name='nome' value='{$row['nome']}' required>
                    <label>Descrição:</label>
                    <textarea name='descricao' required>{$row['descricao']}</textarea>
                    <label>Preço:</label>
                    <input type='number' name='preco' step='0.01' value='{$row['preco']}' required>
                    <label>Categoria:</label>
                    <select name='categoria' required>
                        <option value='categoria1' " . ($row['categoria'] == 'categoria1' ? 'selected' : '') . ">Categoria 1</option>
                        <option value='categoria2' " . ($row['categoria'] == 'categoria2' ? 'selected' : '') . ">Categoria 2</option>
                        <option value='categoria3' " . ($row['categoria'] == 'categoria3' ? 'selected' : '') . ">Categoria 3</option>
                    </select>
                    <div class='form-buttons'>
                        <button type='submit' name='editar_servico' class='btn-save'>Salvar</button>
                        <button type='button' class='btn-cancel' onclick='toggleEditForm({$row['id_servico']})'>Cancelar</button>
                    </div>
                </form>
            </td></tr>";
        }
    } else {
        echo "<tr><td colspan='5'>Nenhum serviço encontrado.</td></tr>";
    }

    echo "</tbody></table>";

    // Links de paginação
    echo "<div class='pagination'>";
    for ($i = 1; $i <= $totalPaginas; $i++) {
        if ($i == $paginaAtual) {
            echo "<strong>$i</strong> "; // Página atual
        } else {
            echo "<a href='gestao_serviços.php?pagina=$i'>$i</a> "; // Links para outras páginas
        }
    }
    echo "</div>";

    $conn->close();
    ?>
    </body>
    </html>