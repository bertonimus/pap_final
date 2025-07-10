<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil do Utilizador</title>
    
    <link rel="stylesheet" href="styles/footer.css">
    <link rel="stylesheet" href="styles/livechat.css">
    <link rel="stylesheet" href="styles/header2.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <link rel="stylesheet" href="profile.css">
</head>
<style>
    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 20px;
        background-color: #f8f9fa;
    }

    .navbar-list {
        list-style: none;
        display: flex;
        gap: 25px;
        margin-left: 40px;
        padding: 0;
    }

    .navbar-list a {
        text-decoration: none;
        color: #333;
        font-weight: bold;
    }

    .navbar-list a.active {
        color: #28a745;
    }
</style>
<body>
    <!-- Navbar Section - Keep Unchanged -->
    <nav class="navbar">
        <h1>Berto</h1>
        <ul class="navbar-list">
            <li><a href="pagina_inicial_com_login">inicio</a></li>
            <li><a href="produtos.php">produtos</a></li>
            <li><a href="serviços_login.php">serviços</a></li>
            <li><a href="#">sobre</a></li>
        </ul>

        <div class="profile-dropdown">
            <div onclick="toggle()" class="profile-dropdown-btn">
                <div class="profile-img">
                    <i class="fa-solid fa-circle"></i>
                </div>
                <span>
                    Username
                    <i class="fa-solid fa-angle-down"></i>
                </span>
            </div>

            <ul class="profile-dropdown-list">
                <li class="profile-dropdown-list-item">
                    <a href="">
                        <i class="fa-regular fa-user"></i>
                        Editar Perfil
                    </a>
                </li>
                <li class="profile-dropdown-list-item">
                    <a href="utilizador/profile/index.php">
                        <i class="fa-solid fa-sliders"></i>
                        Settings
                    </a>
                </li>
                <li class="profile-dropdown-list-item">
                    <a href="utilizador/gestao_produtos.php">
                        <i class="fa-regular fa-circle-question"></i>
                        Gestão de produtos
                    </a>
                </li>
                <hr/>
                <li class="profile-dropdown-list-item">
                    <form id="logout-form" action="utilizador/logout.php" method="POST">
                        <input type="hidden" name="botaoLogout">
                        <a href="#" onclick="document.getElementById('logout-form').submit();">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            Log out
                        </a>
                    </form>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Profile Section - New Content -->
    <main class="profile-container">
        <div class="profile-header">
            <div class="cover-photo">
                <button class="edit-cover-btn"><i class="fas fa-camera"></i></button>
            </div>
            <div class="profile-info">
                <div class="profile-avatar">
                    <img src="https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg" alt="Profile Photo">
                    <button class="edit-avatar-btn"><i class="fas fa-camera"></i></button>
                </div>
                <div class="profile-details">
                    <h1 class="profile-name">João Silva</h1>
                    <p class="profile-username">@joaosilva</p>
                    <p class="profile-bio">Designer gráfico e entusiasta de tecnologia. Apaixonado por criar experiências visualmente envolventes.</p>
                </div>
                <div class="profile-actions">
                    <button class="edit-profile-btn"><i class="fas fa-pen"></i> Editar Perfil</button>
                    <button class="share-profile-btn"><i class="fas fa-share-alt"></i></button>
                </div>
            </div>
        </div>

        <div class="profile-tabs">
            <button class="tab-btn active" data-tab="about">Sobre</button>
            <button class="tab-btn" data-tab="activity">Atividade</button>
            <button class="tab-btn" data-tab="orders">Pedidos</button>
            <button class="tab-btn" data-tab="favorites">Favoritos</button>
        </div>

        <div class="profile-content">
            <!-- About Tab -->
            <div class="tab-content active" id="about">
                <div class="profile-section personal-info">
                    <div class="section-header">
                        <h2>Informação Pessoal</h2>
                        <button class="edit-section-btn"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Nome Completo</span>
                            <span class="info-value">João Carlos Silva</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value">joao.silva@email.com</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Telefone</span>
                            <span class="info-value">+351 912 345 678</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data de Nascimento</span>
                            <span class="info-value">15/04/1990</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Localização</span>
                            <span class="info-value">Lisboa, Portugal</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Profissão</span>
                            <span class="info-value">Designer Gráfico</span>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <div class="section-header">
                        <h2>Redes Sociais</h2>
                        <button class="edit-section-btn"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="social-links">
                        <a href="#" class="social-link facebook">
                            <i class="fab fa-facebook-f"></i>
                            <span>Facebook</span>
                        </a>
                        <a href="#" class="social-link twitter">
                            <i class="fab fa-twitter"></i>
                            <span>Twitter</span>
                        </a>
                        <a href="#" class="social-link instagram">
                            <i class="fab fa-instagram"></i>
                            <span>Instagram</span>
                        </a>
                        <a href="#" class="social-link linkedin">
                            <i class="fab fa-linkedin-in"></i>
                            <span>LinkedIn</span>
                        </a>
                    </div>
                </div>

                <div class="profile-section">
                    <div class="section-header">
                        <h2>Conquistas</h2>
                    </div>
                    <div class="achievements-grid">
                        <div class="achievement-item">
                            <div class="achievement-icon gold">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="achievement-details">
                                <h3>Cliente Fiel</h3>
                                <p>Membro há mais de 1 ano</p>
                            </div>
                        </div>
                        <div class="achievement-item">
                            <div class="achievement-icon silver">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="achievement-details">
                                <h3>Comprador Frequente</h3>
                                <p>Mais de 10 compras realizadas</p>
                            </div>
                        </div>
                        <div class="achievement-item">
                            <div class="achievement-icon bronze">
                                <i class="fas fa-comment"></i>
                            </div>
                            <div class="achievement-details">
                                <h3>Comentarista</h3>
                                <p>5 avaliações de produtos</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Tab -->
            <div class="tab-content" id="activity">
                <div class="profile-section">
                    <div class="section-header">
                        <h2>Atividade Recente</h2>
                    </div>
                    <div class="activity-timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="timeline-content">
                                <h3>Compra Realizada</h3>
                                <p>Você comprou "Smartphone XYZ" por €599,99</p>
                                <span class="timeline-date">Há 2 dias</span>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="timeline-content">
                                <h3>Avaliação de Produto</h3>
                                <p>Você avaliou "Headphones ABC" com 5 estrelas</p>
                                <span class="timeline-date">Há 1 semana</span>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="timeline-content">
                                <h3>Adicionado aos Favoritos</h3>
                                <p>Você adicionou "Smart Watch 123" aos favoritos</p>
                                <span class="timeline-date">Há 2 semanas</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Tab -->
            <div class="tab-content" id="orders">
                <div class="profile-section">
                    <div class="section-header">
                        <h2>Meus Pedidos</h2>
                    </div>
                    <div class="orders-list">
                        <div class="order-item">
                            <div class="order-header">
                                <div class="order-id">Pedido #1234</div>
                                <div class="order-date">15/05/2023</div>
                                <div class="order-status delivered">Entregue</div>
                            </div>
                            <div class="order-products">
                                <div class="order-product">
                                    <img src="https://images.pexels.com/photos/47261/pexels-photo-47261.jpeg" alt="Smartphone">
                                    <div class="product-details">
                                        <h3>Smartphone XYZ</h3>
                                        <p>Cor: Preto, Memória: 128GB</p>
                                        <span class="product-price">€599,99</span>
                                    </div>
                                </div>
                            </div>
                            <div class="order-actions">
                                <button class="order-details-btn">Ver Detalhes</button>
                                <button class="track-order-btn">Rastrear</button>
                            </div>
                        </div>
                        <div class="order-item">
                            <div class="order-header">
                                <div class="order-id">Pedido #1122</div>
                                <div class="order-date">03/04/2023</div>
                                <div class="order-status delivered">Entregue</div>
                            </div>
                            <div class="order-products">
                                <div class="order-product">
                                    <img src="https://images.pexels.com/photos/577769/pexels-photo-577769.jpeg" alt="Headphones">
                                    <div class="product-details">
                                        <h3>Headphones ABC</h3>
                                        <p>Cor: Branco, Tipo: Wireless</p>
                                        <span class="product-price">€149,99</span>
                                    </div>
                                </div>
                            </div>
                            <div class="order-actions">
                                <button class="order-details-btn">Ver Detalhes</button>
                                <button class="track-order-btn">Rastrear</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Favorites Tab -->
            <div class="tab-content" id="favorites">
                <div class="profile-section">
                    <div class="section-header">
                        <h2>Meus Favoritos</h2>
                    </div>
                    <div class="favorites-grid">
                        <div class="favorite-item">
                            <div class="favorite-img">
                                <img src="https://images.pexels.com/photos/437037/pexels-photo-437037.jpeg" alt="Smart Watch">
                                <button class="remove-favorite-btn"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="favorite-details">
                                <h3>Smart Watch 123</h3>
                                <div class="favorite-price">€199,99</div>
                                <button class="add-to-cart-btn">Adicionar ao Carrinho</button>
                            </div>
                        </div>
                        <div class="favorite-item">
                            <div class="favorite-img">
                                <img src="https://images.pexels.com/photos/1279107/pexels-photo-1279107.jpeg" alt="Wireless Earbuds">
                                <button class="remove-favorite-btn"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="favorite-details">
                                <h3>Wireless Earbuds Pro</h3>
                                <div class="favorite-price">€129,99</div>
                                <button class="add-to-cart-btn">Adicionar ao Carrinho</button>
                            </div>
                        </div>
                        <div class="favorite-item">
                            <div class="favorite-img">
                                <img src="https://images.pexels.com/photos/1334597/pexels-photo-1334597.jpeg" alt="Laptop Ultra">
                                <button class="remove-favorite-btn"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="favorite-details">
                                <h3>Laptop Ultra</h3>
                                <div class="favorite-price">€1299,99</div>
                                <button class="add-to-cart-btn">Adicionar ao Carrinho</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer Section - Keep Unchanged -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="footer-col">
                    <h4>company</h4>
                    <ul>
                        <li><a href="#">about us</a></li>
                        <li><a href="#">our services</a></li>
                        <li><a href="#">privacy policy</a></li>
                        <li><a href="#">affiliate program</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>get help</h4>
                    <ul>
                        <li><a href="#">faq</a></li>
                        <li><a href="#">shipping</a></li>
                        <li><a href="#">returns</a></li>
                        <li><a href="#">order status</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>follow us</h4>
                    <ul>
                        <li><a href="#">facebook</a></li>
                        <li><a href="#">twitter</a></li>
                        <li><a href="#">instagram</a></li>
                        <li><a href="#">youtube</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <script src="scripts/header.js"></script>
    <script src="chat/chat.js"></script>
    <script src="profile.js"></script>
</body>
</html>