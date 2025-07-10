<?php
session_start();

if (!isset($_SESSION['id_utilizadores'])) {
    header("Location: logintexte.php");
    exit();
}

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
$user_id = $_SESSION['id_utilizadores'];

// Obter dados da oferta aceita através da URL ou da tabela accepted_offers
$offer_data = null;

if (isset($_GET['offer_id'])) {
    $offer_id = (int)$_GET['offer_id'];
    
    // Buscar na tabela accepted_offers
    $stmt = $conn->prepare("
        SELECT ao.*, o.valor, o.remetente_id, o.destinatario_id
        FROM accepted_offers ao
        JOIN ofertas o ON ao.offer_id = o.id
        WHERE ao.offer_id = ? AND ao.client_id = ?
    ");
    $stmt->bind_param("ii", $offer_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $offer_data = $result->fetch_assoc();
    $stmt->close();
}

// Se não encontrou na tabela accepted_offers, verificar na sessão (fallback)
if (!$offer_data && isset($_SESSION['accepted_offer'])) {
    $offer_data = $_SESSION['accepted_offer'];
}

// Se ainda não tem dados, redirecionar
if (!$offer_data) {
    $_SESSION['error_message'] = "Dados da oferta não encontrados. Por favor, tente novamente.";
    header("Location: messages.php");
    exit();
}

// VERIFICAÇÃO CRÍTICA: Apenas quem deve pagar pode acessar o checkout
// QUEM PAGA = Quem CRIOU o serviço (cliente)
$stmt = $conn->prepare("
    SELECT o.*, s.id_utilizador as service_creator_id
    FROM ofertas o
    LEFT JOIN mensagens m ON (m.remetente_id = o.remetente_id AND m.destinatario_id = o.destinatario_id) 
        OR (m.remetente_id = o.destinatario_id AND m.destinatario_id = o.remetente_id)
    LEFT JOIN servicos s ON m.servico_id = s.id_servico
    WHERE o.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $offer_data['offer_id']);
$stmt->execute();
$offer_result = $stmt->get_result();
$offer = $offer_result->fetch_assoc();
$stmt->close();

if ($offer) {
    $service_creator_id = $offer['service_creator_id'];
    if ($service_creator_id) {
        // QUEM PAGA = Quem CRIOU o serviço
        $payer_id = $service_creator_id;
        $provider_id = ($service_creator_id == $offer['remetente_id']) ? $offer['destinatario_id'] : $offer['remetente_id'];
    } else {
        // Fallback: usar dados da tabela accepted_offers
        $payer_id = $offer_data['client_id'];
        $provider_id = $offer_data['provider_id'];
    }
    
    // Verificar se o usuário atual é quem deve pagar
    if ($payer_id != $user_id) {
        $_SESSION['error_message'] = "Acesso negado: Apenas quem deve pagar pode acessar o checkout.";
        header("Location: checkout.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Oferta não encontrada.";
    header("Location: messages.php");
    exit();
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    try {
        $conn->begin_transaction();
        
        // Simulate payment processing (replace with real payment gateway)
        $payment_amount = $offer_data['valor'];
        
        // Record payment
        $stmt = $conn->prepare("
            INSERT INTO pagamentos_servicos (usuario_id, offer_id, valor, tipo, status) 
            VALUES (?, ?, ?, 'inicial', 'pago')
        ");
        $stmt->bind_param("iid", $user_id, $offer_data['offer_id'], $payment_amount);
        $stmt->execute();
        $stmt->close();
        
        // Update offer status
        $stmt = $conn->prepare("UPDATE ofertas SET status = 'pago_inicial' WHERE id = ?");
        $stmt->bind_param("i", $offer_data['offer_id']);
        $stmt->execute();
        $stmt->close();
        
        // Create escrow transaction
        $escrow_id = $escrow_system->createEscrowTransaction(
            $offer_data['offer_id'],
            $payer_id,
            $provider_id,
            $payment_amount
        );
        
        // Send confirmation messages
        $payment_message = "💳 Pagamento confirmado: €" . number_format($payment_amount, 2) . " - Escrow ativado. Aguardando início do serviço.";
        $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, data_envio, tipo) VALUES (?, ?, ?, NOW(), 'sistema')");
        $stmt->bind_param("iis", $user_id, $provider_id, $payment_message);
        $stmt->execute();
        $stmt->close();
        
        $provider_message = "🎉 Cliente efetuou o pagamento! Valor protegido por escrow. Inicie o serviço e submeta provas de entrega.";
        $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, data_envio, tipo) VALUES (?, ?, ?, NOW(), 'sistema')");
        $stmt->bind_param("iis", $provider_id, $user_id, $provider_message);
        $stmt->execute();
        $stmt->close();
        
        // Remove from accepted_offers table
        $stmt = $conn->prepare("DELETE FROM accepted_offers WHERE offer_id = ?");
        $stmt->bind_param("i", $offer_data['offer_id']);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // Clear session data
        unset($_SESSION['accepted_offer']);
        
        $success = true;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erro no pagamento: " . $e->getMessage();
    }
}

$nome_usuario = $_SESSION['utilizador'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berto - Checkout Seguro</title>
    <link rel="stylesheet" href="styles/header2.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />
    <style>
        :root {
            --primary-color: #059669;
            --primary-hover: #047857;
            --primary-light: #10b981;
            --secondary-color: #6b7280;
            --background-color: #fafafa;
            --card-background: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --shadow-soft: 0 2px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 4px 25px rgba(0, 0, 0, 0.12);
            --shadow-strong: 0 8px 40px rgba(0, 0, 0, 0.15);
            --shadow-glow: 0 0 20px rgba(16, 185, 129, 0.3);
            --transition-speed: 0.3s;
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --success-color: #10b981;
            --error-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .main-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .checkout-card {
            background: var(--card-background);
            border-radius: var(--border-radius);
            padding: 3rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .checkout-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #D1FAE5;
            color: #065F46;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .payer-notice {
            background: #FEF3C7;
            border: 1px solid #FCD34D;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .payer-notice h3 {
            color: #92400E;
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .payer-notice p {
            color: #92400E;
            font-size: 0.875rem;
        }

        .order-summary {
            background: #F9FAFB;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .order-summary h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--primary-color);
        }

        .escrow-info {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .escrow-info h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1E40AF;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .escrow-features {
            list-style: none;
            padding: 0;
        }

        .escrow-features li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: #1E40AF;
            font-size: 0.875rem;
        }

        .payment-form {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all var(--transition-speed);
            background: var(--card-background);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .payment-btn {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .success-message {
            text-align: center;
            padding: 3rem 2rem;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .success-icon i {
            font-size: 2rem;
            color: white;
        }

        .success-message h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .success-message p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all var(--transition-speed);
        }

        .back-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            text-decoration: none;
            color: white;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: #991b1b;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .checkout-card {
                padding: 2rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <h1>Berto</h1>
        <ul class="navbar-list">
            <li><a href="index.php">Início</a></li>
            <li><a href="produtos.php">Produtos</a></li>
            <li><a href="servicos_resultados.php">Serviços</a></li>
            <li><a href="suporte.php">Suporte</a></li>
            <li><a href="messages.php">Mensagens</a></li>
            <li><a href="#">Sobre</a></li>
        </ul>

        <div class="profile-dropdown">
            <div onclick="toggle()" class="profile-dropdown-btn">
                <div class="profile-img">
                    <i class="fa-solid fa-user"></i>
                </div>
                <span>
                    <?php echo htmlspecialchars($nome_usuario); ?>
                    <i class="fa-solid fa-chevron-down"></i>
                </span>
            </div>

            <ul class="profile-dropdown-list">
                <li class="profile-dropdown-list-item">
                    <a href="utilizador/profile/index.php">
                        <i class="fa-regular fa-user"></i>
                        Editar Perfil
                    </a>
                </li>
                <li class="profile-dropdown-list-item">
                    <a href="delivery_proof.php">
                        <i class="fa-solid fa-truck"></i>
                        Provas de Entrega
                    </a>
                </li>
                <li class="profile-dropdown-list-item">
                    <a href="utilizador/gestao_produtos.php">
                        <i class="fa-solid fa-box"></i>
                        Gestão de Produtos
                    </a>
                </li>
                <hr />
                <li class="profile-dropdown-list-item">
                    <form id="logout-form" action="utilizador/logout.php" method="POST">
                        <input type="hidden" name="botaoLogout">
                        <a href="#" onclick="document.getElementById('logout-form').submit();">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            Sair
                        </a>
                    </form>
                </li>
            </ul>
        </div>
    </nav>

    <main class="main-container">
        <div class="checkout-card">
            <?php if (isset($success) && $success): ?>
                <div class="success-message">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2>Pagamento Confirmado!</h2>
                    <p>Seu pagamento foi processado com sucesso e está protegido pelo sistema de escrow. O prestador foi notificado e pode iniciar o serviço.</p>
                    <a href="messages.php?destinatario_id=<?= $provider_id ?>" class="back-btn">
                        <i class="fas fa-comments"></i>
                        Voltar às Mensagens
                    </a>
                </div>
            <?php else: ?>
                <div class="checkout-header">
                    <h1>Checkout Seguro</h1>
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        Protegido por Escrow
                    </div>
                </div>

                <div class="payer-notice">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Confirmação de Pagamento
                    </h3>
                    <p><strong>Você está efetuando o pagamento porque CRIOU este serviço.</strong> O sistema identifica automaticamente quem deve pagar baseado no criador do serviço original.</p>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <div class="order-summary">
                    <h3>Resumo do Pedido</h3>
                    <div class="summary-row">
                        <span>Valor do Serviço</span>
                        <span>€<?= number_format($offer_data['valor'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Taxa de Processamento</span>
                        <span>€0.00</span>
                    </div>
                    <div class="summary-row">
                        <span><strong>Total a Pagar</strong></span>
                        <span><strong>€<?= number_format($offer_data['valor'], 2) ?></strong></span>
                    </div>
                </div>

                <div class="escrow-info">
                    <h4>
                        <i class="fas fa-shield-alt"></i>
                        Como Funciona o Sistema de Escrow
                    </h4>
                    <ul class="escrow-features">
                        <li><i class="fas fa-check"></i> Seu pagamento fica protegido até a conclusão do serviço</li>
                        <li><i class="fas fa-check"></i> 50% é liberado após a primeira entrega do prestador</li>
                        <li><i class="fas fa-check"></i> 50% restante é liberado após sua confirmação final</li>
                        <li><i class="fas fa-check"></i> Reembolso automático se não houver entrega em 72h</li>
                        <li><i class="fas fa-check"></i> Sistema de disputas para resolver conflitos</li>
                    </ul>
                </div>

                <form method="POST" class="payment-form" onsubmit="return confirm('Confirmar pagamento de €<?= number_format($offer_data['valor'], 2) ?>?')">
                    <div class="form-group">
                        <label for="card_number">Número do Cartão</label>
                        <input type="text" id="card_number" placeholder="1234 5678 9012 3456" required maxlength="19">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry">Validade</label>
                            <input type="text" id="expiry" placeholder="MM/AA" required>
                        </div>
                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <input type="text" id="cvv" placeholder="123" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cardholder">Nome no Cartão</label>
                        <input type="text" id="cardholder" placeholder="Nome completo" required>
                    </div>

                    <button type="submit" name="confirm_payment" class="payment-btn">
                        <i class="fas fa-lock"></i>
                        Confirmar Pagamento Seguro - €<?= number_format($offer_data['valor'], 2) ?>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1rem;">
                    <a href="messages.php?destinatario_id=<?= $provider_id ?>" style="color: var(--text-secondary); text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Voltar às mensagens
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggle() {
            document.querySelector('.profile-dropdown').classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.profile-dropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Format card number input
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Format expiry input
        document.getElementById('expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // CVV input restriction
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>