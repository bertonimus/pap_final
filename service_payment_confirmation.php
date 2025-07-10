<?php
session_start();

// Database connection
$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "gestao_utilizadores";

$con = new mysqli($servidor, $usuario, $senha, $banco);

if ($con->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed']));
}

// Verify user is logged in
if (!isset($_SESSION['id_utilizadores'])) {
    header('Location: index.php');
    exit();
}

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$usuario_id = $_SESSION['id_utilizadores'];

// Get user email
$email_stmt = $con->prepare("SELECT email FROM utilizadores WHERE id_utilizadores = ?");
$email_stmt->bind_param("i", $usuario_id);
$email_stmt->execute();
$email_result = $email_stmt->get_result();
$user_email = $email_result->fetch_assoc()['email'];

// Get payment details
$payment = null;

$stmt = $con->prepare("
    SELECT ps.*, o.valor as total_valor, u.utilizador as prestador_nome
    FROM pagamentos_servicos ps
    JOIN ofertas o ON ps.offer_id = o.id
    JOIN utilizadores u ON o.destinatario_id = u.id_utilizadores
    WHERE ps.id = ? AND ps.usuario_id = ?
");

$stmt->bind_param("ii", $payment_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $payment = $result->fetch_assoc();
}

if (!$payment) {
    header('Location: messages.php');
    exit();
}

// Send email notification
$subject = "Confirmação de Pagamento de Serviço #" . $payment_id;
$message = "Olá,\n\n";
$message .= "Seu pagamento inicial foi processado com sucesso!\n\n";
$message .= "------------------------------------------\n";
$message .= "Detalhes do Pagamento:\n";
$message .= "ID do Pagamento: #" . $payment_id . "\n";
$message .= "Prestador: " . $payment['prestador_nome'] . "\n";
$message .= "Valor Pago (50%): €" . number_format($payment['valor'], 2) . "\n";
$message .= "Valor Total do Serviço: €" . number_format($payment['total_valor'], 2) . "\n";
$message .= "Status: " . ucfirst($payment['status']) . "\n";
$message .= "Data: " . date("d/m/Y H:i", strtotime($payment['created_at'])) . "\n\n";
$message .= "O restante (€" . number_format($payment['total_valor'] - $payment['valor'], 2) . ") será pago após a conclusão do serviço.\n\n";
$message .= "Caso tenha dúvidas, entre em contato conosco.\n\n";
$message .= "Atenciosamente,\n";
$message .= "Equipe Berto.com\n";

$headers = "From: Berto <no-reply@Berto.com>\r\n";
$headers .= "Reply-To: suporte@Berto.com\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";

mail($user_email, $subject, $message, $headers);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Confirmado</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />
    <style>
        :root {
            --primary-color: #3b82f6;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.5;
            background-color: var(--gray-50);
            color: var(--gray-800);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .confirmation-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .confirmation-icon {
            width: 4rem;
            height: 4rem;
            background-color: var(--success-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }

        .confirmation-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
        }

        .service-notice {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 1px solid var(--warning-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem 0;
        }

        .service-notice h3 {
            color: #92400E;
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }

        .service-notice p {
            color: #78350F;
            font-size: 0.875rem;
        }

        .payment-details {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .payment-number {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .payment-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: var(--success-color);
            color: white;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .payment-summary {
            background-color: var(--gray-50);
            border-radius: 0.375rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            color: var(--gray-800);
            font-size: 1.125rem;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid var(--gray-200);
        }

        .remaining-payment {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem 0;
        }

        .remaining-payment h4 {
            color: #1E40AF;
            margin-bottom: 0.5rem;
        }

        .remaining-payment p {
            color: #1E3A8A;
            font-size: 0.875rem;
        }

        .button {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 0.375rem;
            font-weight: 500;
            margin-top: 2rem;
            transition: background-color 0.15s ease-in-out;
        }

        .button:hover {
            background-color: #2563eb;
            text-decoration: none;
            color: white;
        }

        .button-secondary {
            background-color: var(--gray-600);
            margin-right: 1rem;
        }

        .button-secondary:hover {
            background-color: var(--gray-700);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-card">
            <div class="confirmation-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="confirmation-title">Pagamento Inicial Confirmado!</h1>
            <p>Seu pagamento foi processado com sucesso. O prestador foi notificado e pode iniciar o serviço.</p>
        </div>

        <div class="service-notice">
            <h3><i class="fas fa-info-circle"></i> Sobre o Pagamento</h3>
            <p>Este foi um pagamento inicial de 50% do valor total acordado. O restante será pago automaticamente após a conclusão e confirmação do serviço.</p>
        </div>

        <div class="payment-details">
            <div class="payment-header">
                <div class="payment-number">Pagamento #<?php echo $payment['id']; ?></div>
                <div class="payment-status"><?php echo ucfirst($payment['status']); ?></div>
            </div>

            <div class="payment-summary">
                <div class="summary-row">
                    <span>Prestador</span>
                    <span><?php echo htmlspecialchars($payment['prestador_nome']); ?></span>
                </div>
                <div class="summary-row">
                    <span>Valor Total do Serviço</span>
                    <span>€<?php echo number_format($payment['total_valor'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Pagamento Inicial (50%)</span>
                    <span>€<?php echo number_format($payment['valor'], 2); ?></span>
                </div>
                <div class="summary-total">
                    <span>Pago Agora</span>
                    <span>€<?php echo number_format($payment['valor'], 2); ?></span>
                </div>
            </div>

            <div class="remaining-payment">
                <h4><i class="fas fa-clock"></i> Pagamento Restante</h4>
                <p>€<?php echo number_format($payment['total_valor'] - $payment['valor'], 2); ?> será pago automaticamente após a conclusão do serviço e confirmação de ambas as partes.</p>
            </div>

            <div style="text-align: center;">
                <a href="messages.php" class="button button-secondary">Voltar às Mensagens</a>
                <a href="resultados.php" class="button">Procurar Mais Serviços</a>
            </div>
        </div>
    </div>
</body>
</html>