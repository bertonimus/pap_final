<?php
/**
 * Script CRON para verificações automáticas do sistema de escrow
 * Deve ser executado a cada hora
 */

require_once 'escrow_system.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestao_utilizadores";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$escrow_system = new EscrowSystem($conn);

try {
    // Executar verificações de reembolso automático
    $refunds_processed = $escrow_system->runAutoRefundCheck();
    
    echo "Verificação de escrow concluída.\n";
    echo "Reembolsos automáticos processados: {$refunds_processed}\n";
    
    // Log da execução
    error_log("Cron escrow check executed - Refunds: {$refunds_processed}");
    
} catch (Exception $e) {
    error_log("Erro no cron de escrow: " . $e->getMessage());
    echo "Erro: " . $e->getMessage() . "\n";
}

$conn->close();
?>