<?php
session_start();

// Configuração da base de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestao_utilizadores";

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Verificar se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitizar e validar dados
    $nome = trim($_POST['nome']);
    $email = isset($_POST['email']) ? trim($_POST['email']) : ($_SESSION['email'] ?? '');
    $categoria = trim($_POST['categoria']);
    $prioridade = trim($_POST['prioridade']);
    $assunto = trim($_POST['assunto']);
    $mensagem = trim($_POST['mensagem']);
    $id_utilizador = $_SESSION['id_utilizadores'] ?? null;
    
    // Validações básicas
    $errors = [];
    
    if (empty($nome) || strlen($nome) < 2) {
        $errors[] = "Nome deve ter pelo menos 2 caracteres";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido";
    }
    
    if (empty($categoria)) {
        $errors[] = "Categoria é obrigatória";
    }
    
    if (empty($prioridade)) {
        $errors[] = "Prioridade é obrigatória";
    }
    
    if (empty($assunto) || strlen($assunto) < 5) {
        $errors[] = "Assunto deve ter pelo menos 5 caracteres";
    }
    
    if (empty($mensagem) || strlen($mensagem) < 20) {
        $errors[] = "Mensagem deve ter pelo menos 20 caracteres";
    }
    
    // Verificar se existem erros
    if (!empty($errors)) {
        $_SESSION['support_errors'] = $errors;
        $_SESSION['support_form_data'] = $_POST;
        header("Location: suporte.php?error=validation");
        exit();
    }
    
    try {
        // Preparar e executar a query
        $stmt = $conn->prepare("INSERT INTO suporte_tickets (id_utilizador, nome, email, categoria, prioridade, assunto, mensagem, status, data_criacao) VALUES (?, ?, ?, ?, ?, ?, ?, 'aberto', NOW())");
        
        if (!$stmt) {
            throw new Exception("Erro na preparação da query: " . $conn->error);
        }
        
        $stmt->bind_param("issssss", $id_utilizador, $nome, $email, $categoria, $prioridade, $assunto, $mensagem);
        
        if ($stmt->execute()) {
            $ticket_id = $conn->insert_id;
            
            // Limpar dados da sessão
            unset($_SESSION['support_errors']);
            unset($_SESSION['support_form_data']);
            
            // Definir mensagem de sucesso
            $_SESSION['support_success'] = "Ticket #$ticket_id criado com sucesso! Nossa equipe entrará em contato em breve.";
            
            // Enviar email de confirmação (opcional)
            sendConfirmationEmail($email, $nome, $ticket_id, $assunto);
            
            header("Location: suporte.php?success=1");
            exit();
        } else {
            throw new Exception("Erro ao executar query: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Erro no processamento do suporte: " . $e->getMessage());
        $_SESSION['support_errors'] = ["Erro interno do servidor. Tente novamente mais tarde."];
        $_SESSION['support_form_data'] = $_POST;
        header("Location: suporte.php?error=server");
        exit();
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
        $conn->close();
    }
} else {
    // Se não foi POST, redirecionar para a página de suporte
    header("Location: suporte.php");
    exit();
}

// Função para enviar email de confirmação
function sendConfirmationEmail($email, $nome, $ticket_id, $assunto) {
    $to = $email;
    $subject = "Confirmação de Ticket #$ticket_id - Berto Suporte";
    
    $message = "
    <html>
    <head>
        <title>Confirmação de Ticket de Suporte</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #059669; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .ticket-info { background: white; padding: 15px; border-left: 4px solid #059669; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Berto - Centro de Suporte</h1>
            </div>
            <div class='content'>
                <h2>Olá, $nome!</h2>
                <p>Recebemos sua solicitação de suporte e criamos o ticket abaixo:</p>
                
                <div class='ticket-info'>
                    <strong>Ticket #$ticket_id</strong><br>
                    <strong>Assunto:</strong> $assunto<br>
                    <strong>Status:</strong> Aberto<br>
                    <strong>Data:</strong> " . date('d/m/Y H:i') . "
                </div>
                
                <p>Nossa equipe de suporte analisará sua solicitação e entrará em contato em breve.</p>
                <p>Tempo estimado de resposta: até 24 horas em dias úteis.</p>
                
                <p>Se precisar de ajuda adicional, responda a este email ou acesse nosso centro de suporte.</p>
            </div>
            <div class='footer'>
                <p>Este é um email automático. Por favor, não responda diretamente.</p>
                <p>&copy; " . date('Y') . " Berto. Todos os direitos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: suporte@berto.com" . "\r\n";
    $headers .= "Reply-To: suporte@berto.com" . "\r\n";
    
    // Enviar email (descomente a linha abaixo para ativar o envio)
    // mail($to, $subject, $message, $headers);
}
?>