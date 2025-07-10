<?php
session_start();

if (!isset($_SESSION['id_utilizadores']) || !isset($_GET['escrow_id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Acesso negado']));
}

require_once 'escrow_system.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestao_utilizadores";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    exit(json_encode(['error' => 'Erro de conexão']));
}

$escrow_system = new EscrowSystem($conn);
$user_id = $_SESSION['id_utilizadores'];
$escrow_id = (int)$_GET['escrow_id'];

// Verificar se o usuário é o prestador deste escrow
/*if (!$escrow_system->isUserProviderOfEscrow($escrow_id, $user_id)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Acesso negado']));
}*/

// Buscar provas existentes para este escrow
$stmt = $conn->prepare("
    SELECT proof_type, status, submitted_at 
    FROM delivery_proofs 
    WHERE escrow_id = ? 
    ORDER BY submitted_at ASC
");
$stmt->bind_param("i", $escrow_id);
$stmt->execute();
$result = $stmt->get_result();

$proofs = [];
while ($row = $result->fetch_assoc()) {
    $proofs[] = $row;
}

header('Content-Type: application/json');
echo json_encode($proofs);

$conn->close();
?>