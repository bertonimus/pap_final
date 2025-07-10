<?php

$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "gestao_utilizadores";

$conn = new mysqli($servidor, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed']));
}

$query = $_GET['q'] ?? '';
$query = $conn->real_escape_string($query);

// Consulta SQL
$sql = "SELECT id, nome, preco, imagem FROM produtos 
        WHERE nome LIKE '%$query%' 
        OR descricao LIKE '%$query%' 
        LIMIT 5";

$result = $conn->query($sql);

if (!$result) {
    die(json_encode(['error' => 'Query failed: ' . $conn->error]));
}

$products = [];

while ($row = $result->fetch_assoc()) {
    // Adiciona o caminho completo da imagem
    $row['imagem'] = 'utilizador/uploads/' . $row['imagem'];
    $products[] = $row;
}

echo json_encode($products);
$conn->close();
?>