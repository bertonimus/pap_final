<?php
/**
 * Script para criar a tabela accepted_offers
 * Execute este arquivo uma vez para criar a estrutura necessária
 */

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestao_utilizadores";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

try {
    echo "<h2>🔧 Configurando Sistema de Remoção de Serviços Pagos</h2>";
    
    // Criar tabela para rastrear ofertas aceitas
    $sql = "CREATE TABLE IF NOT EXISTS accepted_offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        offer_id INT NOT NULL,
        client_id INT NOT NULL,
        provider_id INT NOT NULL,
        service_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (offer_id) REFERENCES ofertas(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES utilizadores(id_utilizadores) ON DELETE CASCADE,
        FOREIGN KEY (provider_id) REFERENCES utilizadores(id_utilizadores) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES servicos(id_servico) ON DELETE SET NULL,
        
        UNIQUE KEY unique_offer (offer_id),
        INDEX idx_service (service_id),
        INDEX idx_client (client_id),
        INDEX idx_provider (provider_id)
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "✅ Tabela 'accepted_offers' criada com sucesso!<br>";
    } else {
        echo "❌ Erro ao criar tabela: " . $conn->error . "<br>";
    }
    
    // Migrar dados existentes de ofertas aceitas
    echo "<br>📦 Migrando dados existentes...<br>";
    
    $migrate_sql = "
        INSERT IGNORE INTO accepted_offers (offer_id, client_id, provider_id, service_id)
        SELECT DISTINCT 
            o.id as offer_id,
            CASE 
                WHEN s.id_utilizador IS NOT NULL THEN s.id_utilizador
                ELSE o.destinatario_id
            END as client_id,
            CASE 
                WHEN s.id_utilizador IS NOT NULL THEN 
                    CASE WHEN s.id_utilizador = o.remetente_id THEN o.destinatario_id ELSE o.remetente_id END
                ELSE o.remetente_id
            END as provider_id,
            s.id_servico as service_id
        FROM ofertas o
        LEFT JOIN mensagens m ON (
            (m.remetente_id = o.remetente_id AND m.destinatario_id = o.destinatario_id) OR
            (m.remetente_id = o.destinatario_id AND m.destinatario_id = o.remetente_id)
        )
        LEFT JOIN servicos s ON m.servico_id = s.id_servico
        WHERE o.status IN ('aceita', 'pago_inicial')
    ";
    
    if ($conn->query($migrate_sql) === TRUE) {
        $migrated_rows = $conn->affected_rows;
        echo "✅ {$migrated_rows} registros migrados com sucesso!<br>";
    } else {
        echo "❌ Erro na migração: " . $conn->error . "<br>";
    }
    
    // Verificar dados migrados
    $check_sql = "SELECT COUNT(*) as total FROM accepted_offers";
    $result = $conn->query($check_sql);
    $count = $result->fetch_assoc()['total'];
    
    echo "<br>📊 <strong>Estatísticas:</strong><br>";
    echo "• Total de ofertas aceitas registradas: {$count}<br>";
    
    // Verificar serviços que serão removidos
    $services_to_remove = "
        SELECT COUNT(DISTINCT s.id_servico) as total
        FROM servicos s
        WHERE s.id_servico IN (
            SELECT DISTINCT ao.service_id 
            FROM accepted_offers ao
            JOIN ofertas o ON ao.offer_id = o.id
            WHERE ao.service_id IS NOT NULL 
            AND o.status IN ('pago_inicial', 'concluida')
        )
    ";
    
    $result = $conn->query($services_to_remove);
    $services_count = $result->fetch_assoc()['total'];
    
    echo "• Serviços que serão removidos da página: {$services_count}<br>";
    
    echo "<br>🎉 <strong>Sistema configurado com sucesso!</strong><br>";
    echo "<div style='background: #d1fae5; border: 1px solid #10b981; border-radius: 8px; padding: 1rem; margin: 1rem 0;'>";
    echo "<h3 style='color: #065f46; margin-bottom: 0.5rem;'>✅ Como funciona agora:</h3>";
    echo "<ul style='color: #065f46; margin-left: 1.5rem;'>";
    echo "<li>Serviços aparecem normalmente na página</li>";
    echo "<li>Quando uma oferta é aceita → Serviço ainda visível</li>";
    echo "<li>Quando o pagamento é efetuado → <strong>Serviço removido automaticamente</strong></li>";
    echo "<li>Apenas serviços disponíveis aparecem para os usuários</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<br>🔗 <a href='servicos_resultados.php' style='background: #059669; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 6px;'>Ver Página de Serviços</a>";
    echo " <a href='messages.php' style='background: #3b82f6; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 6px; margin-left: 0.5rem;'>Ir para Mensagens</a>";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}

$conn->close();
?>

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 900px; 
    margin: 2rem auto; 
    padding: 2rem; 
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    line-height: 1.6;
}

h2 { 
    color: #059669; 
    text-align: center;
    margin-bottom: 2rem;
    font-size: 2rem;
}

a { 
    display: inline-block; 
    padding: 0.75rem 1.5rem; 
    text-decoration: none; 
    border-radius: 8px; 
    font-weight: 600;
    transition: all 0.3s ease;
}

a:hover { 
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

ul {
    line-height: 1.8;
}

li {
    margin-bottom: 0.5rem;
}
</style>
```