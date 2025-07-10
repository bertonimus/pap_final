  <?php
  session_start();
  
  if (!isset($_SESSION['id_utilizadores'])) {
      header("Location: logintexte.php");
      exit();
  }
  
  $servername = "localhost";
  $username = "root";
  $password = "";
  $dbname = "gestao_utilizadores";
  
  $conn = new mysqli($servername, $username, $password, $dbname);
  
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }
  
  $user_id = $_SESSION['id_utilizadores'];
  
  // Processar resposta à oferta
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_offer'])) {
      $offer_id = (int)$_POST['offer_id'];
      $response = $_POST['respond_offer']; // 'accept' ou 'reject'
      $destinatario_id = (int)$_POST['destinatario_id'];
      $produto_id = (int)$_POST['produto_id'];
      
      if ($offer_id > 0 && in_array($response, ['accept', 'reject'])) {
          // Buscar detalhes da oferta
          $stmt = $conn->prepare("
              SELECT o.*, p.nome as produto_nome, p.quantidade as produto_quantidade, u.utilizador as remetente_nome
              FROM ofertas o
              JOIN produtos p ON o.produto_id = p.id
              JOIN utilizadores u ON o.remetente_id = u.id_utilizadores
              WHERE o.id = ? AND o.destinatario_id = ? AND o.status = 'pendente' AND o.tipo = 'produto'
          ");
          $stmt->bind_param("ii", $offer_id, $user_id);
          $stmt->execute();
          $result = $stmt->get_result();
          $offer = $result->fetch_assoc();
          $stmt->close();
          
          if (!$offer) {
              $_SESSION['error_message'] = "❌ Oferta não encontrada ou já foi processada.";
          } else {
              if ($response === 'accept') {
                  // Verificar se ainda há estoque suficiente
                  if ($offer['produto_quantidade'] < $offer['quantidade']) {
                      $_SESSION['error_message'] = "❌ Estoque insuficiente. Disponível: " . $offer['produto_quantidade'];
                  } else {
                      // Iniciar transação
                      $conn->begin_transaction();
                      
                      try {
                          // Atualizar status da oferta para aceita
                          $stmt = $conn->prepare("UPDATE ofertas SET status = 'aceita', data_resposta = NOW() WHERE id = ?");
                          $stmt->bind_param("i", $offer_id);
                          $stmt->execute();
                          $stmt->close();
                          
                          // Atualizar estoque do produto
                          $stmt = $conn->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");
                          $stmt->bind_param("ii", $offer['quantidade'], $offer['produto_id']);
                          $stmt->execute();
                          $stmt->close();
                          
                          // Verificar se existe tabela accepted_offers (similar ao sistema de serviços)
                          $table_check = $conn->query("SHOW TABLES LIKE 'accepted_offers'");
                          if ($table_check->num_rows > 0) {
                              // Inserir na tabela accepted_offers para produtos
                              $stmt = $conn->prepare("
                                  INSERT INTO accepted_offers (offer_id, client_id, provider_id, product_id, product_name, offer_type) 
                                  VALUES (?, ?, ?, ?, ?, 'produto')
                                  ON DUPLICATE KEY UPDATE 
                                      client_id = VALUES(client_id), 
                                      provider_id = VALUES(provider_id),
                                      product_id = VALUES(product_id),
                                      product_name = VALUES(product_name)
                              ");
                              $stmt->bind_param("iiiis", $offer_id, $offer['remetente_id'], $user_id, $offer['produto_id'], $offer['produto_nome']);
                              $stmt->execute();
                              $stmt->close();
                          }
                          
                          // Enviar mensagens automáticas sobre a aceitação (para ambos os usuários)
                          $valor_total = $offer['valor'] * $offer['quantidade'];
                          
                          // Mensagem para quem fez a oferta (comprador)
                          $mensagem_comprador = "✅ Sua oferta foi ACEITA! {$offer['quantidade']}x {$offer['produto_nome']} por €" . number_format($offer['valor'], 2) . " cada (Total: €" . number_format($valor_total, 2) . "). Entre em contato para finalizar a compra.";
                          $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, produto_id, data_envio, tipo) VALUES (?, ?, ?, ?, NOW(), 'sistema')");
                          $stmt->bind_param("iisi", $user_id, $offer['remetente_id'], $mensagem_comprador, $offer['produto_id']);
                          $stmt->execute();
                          $stmt->close();
                          
                          // Mensagem para quem aceitou a oferta (vendedor)
                          $mensagem_vendedor = "✅ Você aceitou a oferta de €" . number_format($valor_total, 2) . " de {$offer['remetente_nome']} para {$offer['quantidade']}x {$offer['produto_nome']}. Aguardando contato do comprador.";
                          $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, produto_id, data_envio, tipo) VALUES (?, ?, ?, ?, NOW(), 'sistema')");
                          $stmt->bind_param("iisi", $offer['remetente_id'], $user_id, $mensagem_vendedor, $offer['produto_id']);
                          $stmt->execute();
                          $stmt->close();
                          
                          // Confirmar transação
                          $conn->commit();
                          
                          $_SESSION['success_message'] = "✅ Oferta aceita com sucesso! Estoque atualizado automaticamente.";
                          
                      } catch (Exception $e) {
                          // Reverter transação em caso de erro
                          $conn->rollback();
                          $_SESSION['error_message'] = "❌ Erro ao processar oferta: " . $e->getMessage();
                      }
                  }
              } else { // reject
                  // Iniciar transação para rejeição
                  $conn->begin_transaction();
                  
                  try {
                      // Atualizar status da oferta para rejeitada
                      $stmt = $conn->prepare("UPDATE ofertas SET status = 'rejeitada', data_resposta = NOW() WHERE id = ?");
                      $stmt->bind_param("i", $offer_id);
                      $stmt->execute();
                      $stmt->close();
                      
                      // Enviar mensagem automática sobre a rejeição
                      $valor_total = $offer['valor'] * $offer['quantidade'];
                      $mensagem_rejeitada = "❌ Sua oferta foi REJEITADA: {$offer['quantidade']}x {$offer['produto_nome']} por €" . number_format($offer['valor'], 2) . " cada (Total: €" . number_format($valor_total, 2) . "). Você pode fazer uma nova proposta.";
                      
                      $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, produto_id, data_envio, tipo) VALUES (?, ?, ?, ?, NOW(), 'sistema')");
                      $stmt->bind_param("iisi", $user_id, $offer['remetente_id'], $mensagem_rejeitada, $offer['produto_id']);
                      $stmt->execute();
                      $stmt->close();
                      
                      // Confirmar transação
                      $conn->commit();
                      
                      $_SESSION['success_message'] = "✅ Oferta rejeitada. O usuário foi notificado.";
                      
                  } catch (Exception $e) {
                      $conn->rollback();
                      $_SESSION['error_message'] = "❌ Erro ao rejeitar oferta: " . $e->getMessage();
                  }
              }
          }
      } else {
          $_SESSION['error_message'] = "❌ Dados inválidos para processar a oferta.";
      }
  }
  
  $conn->close();
  
  // Redirecionar de volta para a página de mensagens com os parâmetros corretos
  $redirect_url = "product_messages.php";
  if (isset($destinatario_id) && $destinatario_id > 0) {
      $redirect_url .= "?destinatario_id=" . $destinatario_id;
      if (isset($produto_id) && $produto_id > 0) {
          $redirect_url .= "&produto_id=" . $produto_id;
      }
  }
  
  header("Location: " . $redirect_url);
  exit();
  ?>