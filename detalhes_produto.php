<?php
include 'ligabd.php'; // Conexão com o banco de dados

if (!isset($con) || $con->connect_error) {
    die("Erro na conexão com o banco de dados: " . $con->connect_error);
}

if (!isset($_GET['id'])) {
    header('Location: produtos.php');
    exit();
}

$id = intval($_GET['id']);
$sql = "SELECT * FROM produtos WHERE id = ?";
$stmt = $con->prepare($sql);

if (!$stmt) {
    die("Erro na preparação da consulta: " . $con->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header('Location: produtos.php');
    exit();
}

// Define a quantidade máxima com base no estoque
$maxQuantity = $product['quantidade'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['nome']); ?> - Detalhes</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: rgb(10, 156, 30);
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition-speed: 0.3s;
            --chat-primary: #007bff;
            --chat-secondary: #6c757d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            min-height: 100vh;
        }

        .product-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            border-radius: 15px;
            overflow: hidden;
            position: relative;
            padding-top: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .product-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-image:hover img {
            transform: scale(1.05);
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .product-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: #2d3436;
            line-height: 1.2;
        }

        .product-price {
            font-size: 2.2rem;
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
        }

        .product-description {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #666;
        }

        .creator-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1.5rem;
            border-radius: 15px;
            color: white;
            margin: 1rem 0;
        }

        .creator-info h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .creator-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .contact-creator {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            background: linear-gradient(135deg, var(--chat-primary) 0%, #0056b3 100%);
            color: white;
            padding: 1.25rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
        }

        .contact-creator:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0, 123, 255, 0.4);
            color: white;
            text-decoration: none;
        }

        .contact-creator:active {
            transform: translateY(-1px);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 1rem;
            transition: all var(--transition-speed);
            padding: 0.5rem 1rem;
            border-radius: 10px;
        }

        .back-button:hover {
            color: #495057;
            background-color: #f8f9fa;
            transform: translateX(-5px);
        }

        .stock-info {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
        }

        .stock-info.out-of-stock {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        @media (max-width: 768px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 1rem;
                margin: 1rem;
            }

            .product-name {
                font-size: 2rem;
            }

            .product-price {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="product-container">
            <div class="product-image">
                <img src="<?php echo htmlspecialchars('utilizador/uploads/' . $product['imagem']); ?>" alt="<?php echo htmlspecialchars($product['nome']); ?>">
            </div>
            <div class="product-info">
                <a href="produtos.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Voltar aos produtos
                </a>
                <h1 class="product-name"><?php echo htmlspecialchars($product['nome']); ?></h1>
                <p class="product-price">€<?php echo number_format($product['preco'], 2); ?></p>
                <p class="product-description"><?php echo htmlspecialchars($product['descricao']); ?></p>
                
                <div class="creator-info">
                    <h4><i class="fas fa-user-circle"></i> Criador do Produto</h4>
                    <p>Contacte diretamente o criador para mais informações, personalização ou dúvidas sobre este produto.</p>
                </div>

                <div class="stock-info <?php echo $maxQuantity > 0 ? '' : 'out-of-stock'; ?>">
                    <?php if ($maxQuantity > 0): ?>
                        <i class="fas fa-check-circle"></i> Em Stock (<?php echo $maxQuantity; ?> disponíveis)
                    <?php else: ?>
                        <i class="fas fa-times-circle"></i> Fora de Stock
                    <?php endif; ?>
                </div>
                
                <a href="messages.php?destinatario_id=<?php echo isset($product['criador_id']) ? $product['criador_id'] : $product['id_utilizador']; ?>" class="contact-creator">
                    <i class="fas fa-comments"></i>
                    Contactar Criador
                </a>
                
                <p style="text-align: center; color: #666; font-size: 0.9rem; margin-top: 1rem;">
                    <i class="fas fa-info-circle"></i> Inicie uma conversa para esclarecer dúvidas ou fazer encomendas personalizadas
                </p>
            </div>
        </div>
    </div>
</body>
</html>
<?php
$stmt->close();
$con->close();
?>