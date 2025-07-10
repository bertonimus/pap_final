<?php
/**
 * Script de Debug Completo para Sistema de Provas de Entrega
 * Execute este arquivo para diagnosticar problemas e entender o funcionamento
 */

session_start();

if (!isset($_SESSION['id_utilizadores'])) {
    die("❌ Faça login primeiro");
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
$nome_usuario = $_SESSION['utilizador'];

echo "<h1>🔍 DEBUG COMPLETO: Sistema de Provas de Entrega</h1>";
echo "<div class='user-info'>";
echo "<p><strong>👤 Usuário:</strong> {$nome_usuario} (ID: {$user_id})</p>";
echo "<p><strong>🕒 Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>📊 1. ANÁLISE DE SERVIÇOS CRIADOS</h2>";

// Verificar serviços criados pelo usuário
$stmt = $conn->prepare("SELECT * FROM servicos WHERE id_utilizador = ? ORDER BY id_servico DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$servicos_criados = $stmt->get_result();

echo "<h3>🎯 Serviços que VOCÊ CRIOU (você é o CLIENTE):</h3>";
if ($servicos_criados->num_rows > 0) {
    echo "<table class='data-table'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Status</th></tr>";
    while ($servico = $servicos_criados->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$servico['id_servico']}</td>";
        echo "<td>{$servico['nome']}</td>";
        echo "<td>{$servico['categoria']}</td>";
        echo "<td>€" . number_format($servico['preco'], 2) . "</td>";
        echo "<td class='status-client'>VOCÊ É CLIENTE</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p class='info-box info'>ℹ️ <strong>Nos serviços acima, você é o CLIENTE.</strong> Você pode receber ofertas e revisar provas de entrega.</p>";
} else {
    echo "<p class='no-data'>❌ Você não criou nenhum serviço ainda.</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>📋 2. ANÁLISE DE OFERTAS DETALHADA</h2>";

// Verificar todas as ofertas do usuário com contexto de serviço
$stmt = $conn->prepare("
    SELECT o.*, 
           u1.utilizador as remetente_nome,
           u2.utilizador as destinatario_nome,
           s.nome as servico_nome,
           s.id_utilizador as servico_criador_id,
           s.categoria as servico_categoria
    FROM ofertas o
    JOIN utilizadores u1 ON o.remetente_id = u1.id_utilizadores
    JOIN utilizadores u2 ON o.destinatario_id = u2.id_utilizadores
    LEFT JOIN mensagens m ON (m.remetente_id = o.remetente_id AND m.destinatario_id = o.destinatario_id) 
        OR (m.remetente_id = o.destinatario_id AND m.destinatario_id = o.remetente_id)
    LEFT JOIN servicos s ON m.servico_id = s.id_servico
    WHERE o.remetente_id = ? OR o.destinatario_id = ?
    ORDER BY o.data_criacao DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$ofertas = $stmt->get_result();

echo "<h3>🔍 Análise Completa das Suas Ofertas:</h3>";
if ($ofertas->num_rows > 0) {
    echo "<table class='data-table'>";
    echo "<tr><th>ID</th><th>Valor</th><th>Status</th><th>Remetente</th><th>Destinatário</th><th>Serviço</th><th>Criador</th><th>Seu Papel</th><th>Pode Submeter Provas?</th></tr>";
    
    $ofertas_como_prestador = 0;
    $ofertas_pagas = 0;
    
    while ($oferta = $ofertas->fetch_assoc()) {
        $seu_papel = "";
        $pode_submeter = "❌ NÃO";
        $classe_papel = "";
        
        if ($oferta['servico_criador_id']) {
            // Lógica baseada no criador do serviço
            if ($oferta['servico_criador_id'] == $user_id) {
                $seu_papel = "CLIENTE (criou o serviço)";
                $classe_papel = "status-client";
            } else {
                $seu_papel = "PRESTADOR (não criou o serviço)";
                $classe_papel = "status-provider";
                $ofertas_como_prestador++;
                if ($oferta['status'] == 'pago_inicial') {
                    $pode_submeter = "✅ SIM";
                    $ofertas_pagas++;
                }
            }
        } else {
            // Fallback se não conseguir identificar o serviço
            if ($oferta['remetente_id'] == $user_id) {
                $seu_papel = "REMETENTE (fez a oferta)";
                $classe_papel = "status-neutral";
            } else {
                $seu_papel = "DESTINATÁRIO (recebeu a oferta)";
                $classe_papel = "status-neutral";
            }
        }
        
        echo "<tr>";
        echo "<td>{$oferta['id']}</td>";
        echo "<td>€" . number_format($oferta['valor'], 2) . "</td>";
        echo "<td class='status-{$oferta['status']}'>{$oferta['status']}</td>";
        echo "<td>{$oferta['remetente_nome']}</td>";
        echo "<td>{$oferta['destinatario_nome']}</td>";
        echo "<td>" . ($oferta['servico_nome'] ?: 'N/A') . "</td>";
        echo "<td>" . ($oferta['servico_criador_id'] ?: 'N/A') . "</td>";
        echo "<td class='{$classe_papel}'><strong>{$seu_papel}</strong></td>";
        echo "<td class='" . ($pode_submeter == '✅ SIM' ? 'can-submit' : 'cannot-submit') . "'><strong>{$pode_submeter}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='summary-box'>";
    echo "<h4>📊 Resumo das Ofertas:</h4>";
    echo "<ul>";
    echo "<li><strong>Total de ofertas:</strong> " . $ofertas->num_rows . "</li>";
    echo "<li><strong>Ofertas como PRESTADOR:</strong> {$ofertas_como_prestador}</li>";
    echo "<li><strong>Ofertas PAGAS (pode submeter provas):</strong> {$ofertas_pagas}</li>";
    echo "</ul>";
    echo "</div>";
    
} else {
    echo "<p class='no-data'>❌ Nenhuma oferta encontrada</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔄 3. ANÁLISE DE ESCROWS (TRANSAÇÕES SEGURAS)</h2>";

// Verificar escrows diretamente na base de dados
$stmt = $conn->prepare("
    SELECT e.*, o.remetente_id, o.destinatario_id, o.valor,
           u1.utilizador as cliente_nome,
           u2.utilizador as prestador_nome,
           s.nome as servico_nome
    FROM escrow_transactions e
    JOIN ofertas o ON e.offer_id = o.id
    JOIN utilizadores u1 ON e.client_id = u1.id_utilizadores
    JOIN utilizadores u2 ON e.provider_id = u2.id_utilizadores
    LEFT JOIN mensagens m ON (m.remetente_id = o.remetente_id AND m.destinatario_id = o.destinatario_id) 
        OR (m.remetente_id = o.destinatario_id AND m.destinatario_id = o.remetente_id)
    LEFT JOIN servicos s ON m.servico_id = s.id_servico
    WHERE e.client_id = ? OR e.provider_id = ?
    ORDER BY e.created_at DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$escrows_db = $stmt->get_result();

echo "<h3>🔍 Escrows (Transações Seguras):</h3>";
if ($escrows_db->num_rows > 0) {
    echo "<table class='data-table'>";
    echo "<tr><th>ID</th><th>Oferta</th><th>Cliente</th><th>Prestador</th><th>Valor Total</th><th>Liberado</th><th>Status</th><th>Serviço</th><th>Seu Papel</th></tr>";
    
    $escrows_como_prestador = 0;
    $escrows_ativos = 0;
    
    while ($escrow = $escrows_db->fetch_assoc()) {
        $seu_papel = "";
        $classe_papel = "";
        
        if ($escrow['client_id'] == $user_id) {
            $seu_papel = "CLIENTE (paga)";
            $classe_papel = "status-client";
        } elseif ($escrow['provider_id'] == $user_id) {
            $seu_papel = "PRESTADOR (trabalha)";
            $classe_papel = "status-provider";
            $escrows_como_prestador++;
            if (in_array($escrow['status'], ['pending', 'partial_released'])) {
                $escrows_ativos++;
            }
        }
        
        echo "<tr>";
        echo "<td>{$escrow['id']}</td>";
        echo "<td>{$escrow['offer_id']}</td>";
        echo "<td>{$escrow['cliente_nome']}</td>";
        echo "<td>{$escrow['prestador_nome']}</td>";
        echo "<td>€" . number_format($escrow['total_amount'], 2) . "</td>";
        echo "<td>€" . number_format($escrow['amount_released'], 2) . "</td>";
        echo "<td class='status-{$escrow['status']}'>{$escrow['status']}</td>";
        echo "<td>" . ($escrow['servico_nome'] ?: 'N/A') . "</td>";
        echo "<td class='{$classe_papel}'><strong>{$seu_papel}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='summary-box'>";
    echo "<h4>📊 Resumo dos Escrows:</h4>";
    echo "<ul>";
    echo "<li><strong>Total de escrows:</strong> " . $escrows_db->num_rows . "</li>";
    echo "<li><strong>Escrows como PRESTADOR:</strong> {$escrows_como_prestador}</li>";
    echo "<li><strong>Escrows ATIVOS (pode submeter provas):</strong> {$escrows_ativos}</li>";
    echo "</ul>";
    echo "</div>";
    
} else {
    echo "<p class='no-data'>❌ <strong>NENHUM ESCROW ENCONTRADO!</strong></p>";
    echo "<div class='warning-box'>";
    echo "<p>Isso significa que não há transações de escrow criadas ainda.</p>";
    echo "<p><strong>Para criar um escrow:</strong></p>";
    echo "<ol>";
    echo "<li>Fazer uma oferta em um serviço</li>";
    echo "<li>Aceitar a oferta</li>";
    echo "<li>Efetuar o pagamento (isso cria o escrow automaticamente)</li>";
    echo "</ol>";
    echo "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>📋 4. ANÁLISE DE PROVAS DE ENTREGA</h2>";

// Verificar provas submetidas
$stmt = $conn->prepare("
    SELECT dp.*, e.total_amount, e.client_id, e.provider_id,
           u1.utilizador as client_name,
           u2.utilizador as provider_name
    FROM delivery_proofs dp
    JOIN escrow_transactions e ON dp.escrow_id = e.id
    JOIN utilizadores u1 ON e.client_id = u1.id_utilizadores
    JOIN utilizadores u2 ON e.provider_id = u2.id_utilizadores
    WHERE dp.provider_id = ? OR e.client_id = ?
    ORDER BY dp.submitted_at DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$provas = $stmt->get_result();

echo "<h3>🔍 Provas de Entrega (Submetidas e Para Revisar):</h3>";
if ($provas->num_rows > 0) {
    echo "<table class='data-table'>";
    echo "<tr><th>ID</th><th>Título</th><th>Tipo</th><th>Status</th><th>Cliente</th><th>Prestador</th><th>Data</th><th>Seu Papel</th></tr>";
    
    $provas_submetidas = 0;
    $provas_para_revisar = 0;
    
    while ($prova = $provas->fetch_assoc()) {
        $seu_papel = "";
        $classe_papel = "";
        
        if ($prova['provider_id'] == $user_id) {
            $seu_papel = "PRESTADOR (submeteu)";
            $classe_papel = "status-provider";
            $provas_submetidas++;
        } elseif ($prova['client_id'] == $user_id) {
            $seu_papel = "CLIENTE (revisa)";
            $classe_papel = "status-client";
            if ($prova['status'] == 'pending_review') {
                $provas_para_revisar++;
            }
        }
        
        echo "<tr>";
        echo "<td>{$prova['id']}</td>";
        echo "<td>" . htmlspecialchars($prova['title']) . "</td>";
        echo "<td>{$prova['proof_type']}</td>";
        echo "<td class='status-{$prova['status']}'>{$prova['status']}</td>";
        echo "<td>{$prova['client_name']}</td>";
        echo "<td>{$prova['provider_name']}</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($prova['submitted_at'])) . "</td>";
        echo "<td class='{$classe_papel}'><strong>{$seu_papel}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='summary-box'>";
    echo "<h4>📊 Resumo das Provas:</h4>";
    echo "<ul>";
    echo "<li><strong>Total de provas:</strong> " . $provas->num_rows . "</li>";
    echo "<li><strong>Provas que você submeteu:</strong> {$provas_submetidas}</li>";
    echo "<li><strong>Provas aguardando sua revisão:</strong> {$provas_para_revisar}</li>";
    echo "</ul>";
    echo "</div>";
    
} else {
    echo "<p class='no-data'>❌ Nenhuma prova de entrega encontrada</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔧 5. TESTE DA FUNÇÃO getProviderEscrows()</h2>";

// Testar a função específica que está sendo usada na página
try {
    $escrows_function = $escrow_system->getProviderEscrows($user_id);
    echo "<h3>🔍 Resultado da função getProviderEscrows():</h3>";
    
    if ($escrows_function->num_rows > 0) {
        echo "<table class='data-table'>";
        echo "<tr><th>ID</th><th>Status</th><th>Valor</th><th>Cliente</th><th>Serviço</th><th>Data</th></tr>";
        
        while ($escrow = $escrows_function->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$escrow['id']}</td>";
            echo "<td class='status-{$escrow['status']}'>{$escrow['status']}</td>";
            echo "<td>€" . number_format($escrow['valor'], 2) . "</td>";
            echo "<td>" . ($escrow['client_name'] ?: 'N/A') . "</td>";
            echo "<td>" . ($escrow['service_name'] ?: 'N/A') . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($escrow['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<div class='success-box'>";
        echo "<p>✅ <strong>A função getProviderEscrows() está retornando dados!</strong></p>";
        echo "<p>Isso significa que você tem escrows como prestador e deveria ver projetos na página de provas.</p>";
        echo "</div>";
    } else {
        echo "<div class='warning-box'>";
        echo "<p>⚠️ <strong>A função getProviderEscrows() não retornou nenhum resultado.</strong></p>";
        echo "<p>Isso explica por que a página de provas está vazia.</p>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error-box'>";
    echo "<p>❌ <strong>Erro ao executar getProviderEscrows():</strong></p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🎯 6. DIAGNÓSTICO FINAL</h2>";

// Diagnóstico baseado nos dados coletados
$escrows_db->data_seek(0); // Reset pointer
$prestador_count = 0;
$escrows_ativos_count = 0;

while ($escrow = $escrows_db->fetch_assoc()) {
    if ($escrow['provider_id'] == $user_id) {
        $prestador_count++;
        if (in_array($escrow['status'], ['pending', 'partial_released'])) {
            $escrows_ativos_count++;
        }
    }
}

if ($escrows_ativos_count > 0) {
    echo "<div class='success-box'>";
    echo "<h3>✅ SISTEMA FUNCIONANDO PERFEITAMENTE!</h3>";
    echo "<p><strong>Você pode submeter provas de entrega!</strong></p>";
    echo "<ul>";
    echo "<li>Você é prestador em <strong>{$prestador_count}</strong> projeto(s)</li>";
    echo "<li>Você tem <strong>{$escrows_ativos_count}</strong> escrow(s) ativo(s)</li>";
    echo "<li>Acesse a página de <a href='delivery_proof.php'>Provas de Entrega</a> para submeter</li>";
    echo "</ul>";
    echo "</div>";
} elseif ($prestador_count > 0) {
    echo "<div class='warning-box'>";
    echo "<h3>⚠️ PROJETOS FINALIZADOS</h3>";
    echo "<p>Você foi prestador em {$prestador_count} projeto(s), mas todos já foram finalizados.</p>";
    echo "<p>Para submeter novas provas, você precisa de projetos ativos.</p>";
    echo "</div>";
} else {
    echo "<div class='info-box'>";
    echo "<h3>ℹ️ VOCÊ É APENAS CLIENTE</h3>";
    echo "<p>Você só participa como cliente (quem paga), não como prestador.</p>";
    echo "<p><strong>Como cliente, você pode:</strong></p>";
    echo "<ul>";
    echo "<li>Criar serviços</li>";
    echo "<li>Receber ofertas</li>";
    echo "<li>Efetuar pagamentos</li>";
    echo "<li>Revisar provas de entrega</li>";
    echo "</ul>";
    echo "<p><strong>Para ser prestador:</strong> Aceite ofertas em serviços criados por outros usuários.</p>";
    echo "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔧 7. AÇÕES RECOMENDADAS</h2>";
echo "<div class='actions-grid'>";
echo "<div class='action-card'>";
echo "<h4>📨 Mensagens</h4>";
echo "<p>Fazer ofertas, aceitar propostas, comunicar com clientes/prestadores</p>";
echo "<a href='messages.php' class='action-btn'>Ir para Mensagens</a>";
echo "</div>";

echo "<div class='action-card'>";
echo "<h4>📋 Provas de Entrega</h4>";
echo "<p>Submeter provas como prestador ou revisar como cliente</p>";
echo "<a href='delivery_proof.php' class='action-btn'>Ir para Provas</a>";
echo "</div>";

echo "<div class='action-card'>";
echo "<h4>🛠️ Criar Serviços</h4>";
echo "<p>Criar novos serviços para receber ofertas</p>";
echo "<a href='utilizador/gestao_servicos.php' class='action-btn'>Criar Serviços</a>";
echo "</div>";

echo "<div class='action-card'>";
echo "<h4>🔍 Explorar Serviços</h4>";
echo "<p>Encontrar serviços para fazer ofertas como prestador</p>";
echo "<a href='servicos_resultados.php' class='action-btn'>Explorar Serviços</a>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>📚 8. COMO FUNCIONA O SISTEMA</h2>";
echo "<div class='how-it-works'>";
echo "<div class='step'>";
echo "<h4>1️⃣ CRIAÇÃO DE SERVIÇO</h4>";
echo "<p>Usuário A cria um serviço → <strong>A é CLIENTE</strong></p>";
echo "</div>";

echo "<div class='step'>";
echo "<h4>2️⃣ OFERTA</h4>";
echo "<p>Usuário B faz oferta no serviço de A → <strong>B é PRESTADOR</strong></p>";
echo "</div>";

echo "<div class='step'>";
echo "<h4>3️⃣ ACEITAÇÃO</h4>";
echo "<p>A aceita a oferta de B → Sistema identifica papéis automaticamente</p>";
echo "</div>";

echo "<div class='step'>";
echo "<h4>4️⃣ PAGAMENTO</h4>";
echo "<p>A (cliente) efetua pagamento → Cria escrow automático</p>";
echo "</div>";

echo "<div class='step'>";
echo "<h4>5️⃣ EXECUÇÃO</h4>";
echo "<p>B (prestador) executa trabalho → Submete provas de entrega</p>";
echo "</div>";

echo "<div class='step'>";
echo "<h4>6️⃣ REVISÃO</h4>";
echo "<p>A (cliente) revisa provas → Aprova/rejeita entregas</p>";
echo "</div>";

echo "<div class='step'>";
echo "<h4>7️⃣ PAGAMENTO</h4>";
echo "<p>Sistema libera pagamento gradualmente → Projeto concluído</p>";
echo "</div>";
echo "</div>";
echo "</div>";

$conn->close();
?>

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1400px; 
    margin: 0 auto; 
    padding: 2rem; 
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    line-height: 1.6;
}

h1 { 
    color: #059669; 
    text-align: center;
    margin-bottom: 2rem;
    font-size: 2.5rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

h2 { 
    color: #047857; 
    border-bottom: 3px solid #10b981;
    padding-bottom: 0.5rem;
    margin-top: 2rem;
}

h3 { 
    color: #065f46; 
    margin: 1.5rem 0 1rem 0;
}

.user-info {
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
}

.section {
    background: white;
    margin: 2rem 0;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
}

.data-table { 
    width: 100%; 
    margin: 1rem 0; 
    background: white; 
    border-radius: 12px; 
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-collapse: collapse;
}

.data-table th { 
    background: linear-gradient(135deg, #059669, #047857);
    color: white; 
    padding: 1rem 0.75rem; 
    font-weight: 600;
    text-align: left;
    font-size: 0.875rem;
}

.data-table td { 
    padding: 0.875rem 0.75rem; 
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.875rem;
}

.data-table tr:hover {
    background: #f9fafb;
}

/* Status Classes */
.status-client { 
    background: #dbeafe !important; 
    color: #1e40af !important; 
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    text-align: center;
}

.status-provider { 
    background: #d1fae5 !important; 
    color: #065f46 !important; 
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    text-align: center;
}

.status-neutral { 
    background: #f3f4f6 !important; 
    color: #374151 !important; 
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    text-align: center;
}

.status-pendente { 
    background: #fef3c7; 
    color: #92400e; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.status-aceita { 
    background: #d1fae5; 
    color: #065f46; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.status-pago_inicial { 
    background: #dbeafe; 
    color: #1e40af; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.status-pending { 
    background: #fef3c7; 
    color: #92400e; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.status-partial_released { 
    background: #d1fae5; 
    color: #065f46; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.status-completed { 
    background: #dcfce7; 
    color: #166534; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.status-pending_review { 
    background: #fef3c7; 
    color: #92400e; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.status-approved { 
    background: #d1fae5; 
    color: #065f46; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.status-rejected { 
    background: #fee2e2; 
    color: #991b1b; 
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.can-submit { 
    background: #d1fae5 !important; 
    color: #065f46 !important; 
    font-weight: 700;
    text-align: center;
}

.cannot-submit { 
    background: #fee2e2 !important; 
    color: #991b1b !important; 
    font-weight: 700;
    text-align: center;
}

/* Message Boxes */
.success-box {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    border: 2px solid #10b981;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1rem 0;
    color: #065f46;
}

.warning-box {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 2px solid #f59e0b;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1rem 0;
    color: #92400e;
}

.info-box {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border: 2px solid #3b82f6;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1rem 0;
    color: #1e40af;
}

.error-box {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border: 2px solid #ef4444;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1rem 0;
    color: #991b1b;
}

.summary-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
}

.no-data {
    text-align: center;
    color: #ef4444;
    font-weight: 600;
    padding: 2rem;
    background: #fee2e2;
    border-radius: 8px;
    margin: 1rem 0;
}

/* Actions Grid */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 1.5rem 0;
}

.action-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.action-btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    margin-top: 1rem;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: linear-gradient(135deg, #047857, #059669);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4);
}

/* How it works */
.how-it-works {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
}

.step {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}

.step h4 {
    color: #059669;
    margin-bottom: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    body {
        padding: 1rem;
    }
    
    .data-table {
        font-size: 0.75rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.5rem 0.25rem;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .how-it-works {
        grid-template-columns: 1fr;
    }
}
</style>