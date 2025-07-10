<?php
session_start();
include 'ligabd.php'; // Certifique-se de que este arquivo está configurado corretamente

// Verifica se a conexão foi estabelecida corretamente
if (!isset($con) || $con->connect_error) {
    die("Erro na conexão com o banco de dados: " . $con->connect_error);
}

// Verifica se o usuário está logado
if (!isset($_SESSION['id_utilizadores'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não está logado.']);
    exit();
}

// Captura o ID do produto e a quantidade escolhida
if (isset($_POST['product_id']) && isset($_POST['quantity'])) {
    $product_id = intval($_POST['product_id']);
    $usuario_id = $_SESSION['id_utilizadores']; // ID do usuário logado
    $quantidade = intval($_POST['quantity']);

    // Verifica a quantidade disponível no banco de dados
    $stmt = $con->prepare("SELECT quantidade FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product || $quantidade > $product['quantidade']) {
        echo json_encode(['success' => false, 'message' => 'Quantidade indisponível no estoque.']);
        exit();
    }

    // Insere ou atualiza o produto no carrinho
    $stmt = $con->prepare("INSERT INTO carrinho (usuario_id, produto_id, quantidade) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantidade = quantidade + ?");
    $stmt->bind_param("iiii", $usuario_id, $product_id, $quantidade, $quantidade);
    
    if ($stmt->execute()) {
        // Consulta para contar o número total de itens no carrinho
        $count_stmt = $con->prepare("SELECT SUM(quantidade) AS total FROM carrinho WHERE usuario_id = ?");
        $count_stmt->bind_param("i", $usuario_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $cartCount = $count_row['total'] ? $count_row['total'] : 0;

        echo json_encode(['success' => true, 'cartCount' => $cartCount]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar produto ao carrinho.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'ID do produto ou quantidade não fornecida.']);
}

$con->close();
?>
