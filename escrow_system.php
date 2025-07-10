<?php
/**
 * Sistema de Escrow e Segurança
 * Gerencia transações em custódia, verificações e liberação de pagamentos
 */

class EscrowSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Cria uma transação de escrow quando uma oferta é aceita
     */
    public function createEscrowTransaction($offer_id, $client_id, $provider_id, $amount) {
        try {
            $this->conn->begin_transaction();
            
            // Verificar se o prestador tem verificações mínimas
            $verification_level = $this->getUserVerificationLevel($provider_id);
            $requires_verification = $verification_level === 'unverified';
            
            // Calcular prazo para primeira entrega (3 dias para novos, 1 dia para verificados)
            $deadline_hours = $verification_level === 'unverified' ? 72 : 24;
            $milestone_deadline = date('Y-m-d H:i:s', strtotime("+{$deadline_hours} hours"));
            $auto_refund_date = date('Y-m-d H:i:s', strtotime("+72 hours"));
            
            // Criar transação de escrow
            $stmt = $this->conn->prepare("
                INSERT INTO escrow_transactions 
                (offer_id, client_id, provider_id, total_amount, amount_held, milestone_deadline, auto_refund_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiiddss", $offer_id, $client_id, $provider_id, $amount, $amount, $milestone_deadline, $auto_refund_date);
            $stmt->execute();
            
            $escrow_id = $this->conn->insert_id;
            
            // Atualizar oferta
            $stmt = $this->conn->prepare("
                UPDATE ofertas 
                SET status = 'escrow_created', requires_verification = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $requires_verification, $offer_id);
            $stmt->execute();
            
            // Log de segurança
            $this->logSecurityAction($client_id, 'payment', [
                'escrow_id' => $escrow_id,
                'amount' => $amount,
                'provider_id' => $provider_id
            ]);
            
            $this->conn->commit();
            return $escrow_id;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Submete prova de entrega - APENAS PRESTADORES PODEM SUBMETER
     * VERIFICAÇÃO BASEADA NO CRIADOR DO SERVIÇO
     */
    public function submitDeliveryProof($escrow_id, $provider_id, $proof_type, $title, $description, $file_data = null, $external_link = null) {
        try {
            // VERIFICAÇÃO CRÍTICA: Confirmar que o usuário é realmente o prestador do escrow
            $stmt = $this->conn->prepare("
                SELECT e.id, e.status, e.milestone_deadline, e.provider_id as escrow_provider_id,
                       o.remetente_id, o.destinatario_id,
                       s.id_utilizador as service_creator_id
                FROM escrow_transactions e
                JOIN ofertas o ON e.offer_id = o.id
                LEFT JOIN mensagens m ON (m.remetente_id = o.remetente_id AND m.destinatario_id = o.destinatario_id) 
                    OR (m.remetente_id = o.destinatario_id AND m.destinatario_id = o.remetente_id)
                LEFT JOIN servicos s ON m.servico_id = s.id_servico
                WHERE e.id = ? AND e.status IN ('pending', 'partial_released')
                LIMIT 1
            ");
            $stmt->bind_param("i", $escrow_id);
            $stmt->execute();
            $escrow = $stmt->get_result()->fetch_assoc();
            
            if (!$escrow) {
                throw new Exception("Escrow não encontrado ou já finalizado");
            }
            
            // NOVA LÓGICA: Verificar quem é o prestador baseado no criador do serviço
            $service_creator_id = $escrow['service_creator_id'];
            $offer_sender = $escrow['remetente_id'];
            $offer_receiver = $escrow['destinatario_id'];
            
            // O prestador é quem NÃO criou o serviço
            if ($service_creator_id) {
                $actual_provider_id = ($service_creator_id == $offer_sender) ? $offer_receiver : $offer_sender;
            } else {
                // Fallback: usar a lógica anterior se não conseguirmos identificar o serviço
                $actual_provider_id = $offer_sender;
            }
            
            // VERIFICAÇÃO DE SEGURANÇA: Apenas o prestador pode submeter provas
            if ($actual_provider_id != $provider_id) {
                throw new Exception("Acesso negado: Apenas o prestador de serviços pode submeter provas de entrega. Você não é o prestador deste serviço.");
            }
            
            // Processar upload de arquivo se fornecido
            $file_path = null;
            $file_type = null;
            $file_size = null;
            
            if ($file_data && $file_data['tmp_name']) {
                $upload_result = $this->processFileUpload($file_data, $escrow_id);
                $file_path = $upload_result['path'];
                $file_type = $upload_result['type'];
                $file_size = $upload_result['size'];
            }
            
            // Inserir prova de entrega
            $stmt = $this->conn->prepare("
                INSERT INTO delivery_proofs 
                (escrow_id, provider_id, proof_type, title, description, file_path, file_type, file_size, external_link) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisssssss", $escrow_id, $provider_id, $proof_type, $title, $description, $file_path, $file_type, $file_size, $external_link);
            $stmt->execute();
            
            $proof_id = $this->conn->insert_id;
            
            // Se é primeira entrega, liberar primeira metade automaticamente
            if ($proof_type === 'partial_delivery' || $proof_type === 'milestone_completion') {
                $this->processFirstMilestoneRelease($escrow_id);
            }
            
            // Notificar o cliente sobre a nova prova
            $this->notifyClientAboutProof($escrow_id, $proof_id, $title);
            
            // Log de segurança
            $this->logSecurityAction($provider_id, 'delivery_proof', [
                'escrow_id' => $escrow_id,
                'proof_type' => $proof_type,
                'proof_id' => $proof_id,
                'service_creator_verification' => true
            ]);
            
            return $proof_id;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Verifica se o usuário é o prestador de um escrow específico
     * NOVA LÓGICA: Baseada no criador do serviço
     */
    public function isUserProviderOfEscrow($escrow_id, $user_id) {
        $stmt = $this->conn->prepare("
            SELECT e.provider_id as escrow_provider_id,
                   o.remetente_id, o.destinatario_id,
                   s.id_utilizador as service_creator_id
            FROM escrow_transactions e
            JOIN ofertas o ON e.offer_id = o.id
            LEFT JOIN mensagens m ON (m.remetente_id = o.remetente_id AND m.destinatario_id = o.destinatario_id) 
                OR (m.remetente_id = o.destinatario_id AND m.destinatario_id = o.remetente_id)
            LEFT JOIN servicos s ON m.servico_id = s.id_servico
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $escrow_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            return false;
        }
        
        // Determinar quem é o prestador baseado no criador do serviço
        $service_creator_id = $result['service_creator_id'];
        $offer_sender = $result['remetente_id'];
        $offer_receiver = $result['destinatario_id'];
        
        if ($service_creator_id) {
            // O prestador é quem NÃO criou o serviço
            $actual_provider_id = ($service_creator_id == $offer_sender) ? $offer_receiver : $offer_sender;
        } else {
            // Fallback
            $actual_provider_id = $offer_sender;
        }
        
        return $actual_provider_id == $user_id;
    }
    
    /**
     * Obtém provas de um escrow específico
     */
    public function getEscrowProofs($escrow_id) {
        $stmt = $this->conn->prepare("
            SELECT * FROM delivery_proofs 
            WHERE escrow_id = ? 
            ORDER BY submitted_at ASC
        ");
        $stmt->bind_param("i", $escrow_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Obtém escrows onde o usuário é prestador
     * CORRIGIDA: Prestador é quem NÃO criou o serviço
     */
    public function getProviderEscrows($provider_id) {
        $stmt = $this->conn->prepare("
            SELECT DISTINCT e.id, e.status, e.total_amount, e.created_at,
                   o.id as offer_id, o.valor, o.remetente_id, o.destinatario_id,
                   CASE 
                       WHEN srv.id_utilizador IS NOT NULL THEN
                           CASE 
                               WHEN srv.id_utilizador != ? THEN
                                   CASE 
                                       WHEN srv.id_utilizador = o.remetente_id THEN u_dest.utilizador 
                                       ELSE u_rem.utilizador 
                                   END
                               ELSE NULL
                           END
                       ELSE 
                           CASE 
                               WHEN e.provider_id = ? THEN
                                   CASE 
                                       WHEN e.client_id = o.remetente_id THEN u_rem.utilizador
                                       ELSE u_dest.utilizador
                                   END
                               ELSE NULL
                           END
                   END as client_name,
                   srv.nome as service_name,
                   srv.id_utilizador as service_creator_id
            FROM escrow_transactions e
            JOIN ofertas o ON e.offer_id = o.id
            JOIN utilizadores u_rem ON o.remetente_id = u_rem.id_utilizadores
            JOIN utilizadores u_dest ON o.destinatario_id = u_dest.id_utilizadores
            LEFT JOIN (
                SELECT DISTINCT m.remetente_id, m.destinatario_id, s.id_servico, s.nome, s.id_utilizador
                FROM mensagens m 
                JOIN servicos s ON m.servico_id = s.id_servico
                WHERE m.servico_id IS NOT NULL
            ) srv ON (srv.remetente_id = o.remetente_id AND srv.destinatario_id = o.destinatario_id) 
                  OR (srv.remetente_id = o.destinatario_id AND srv.destinatario_id = o.remetente_id)
            WHERE e.status IN ('pending', 'partial_released')
            AND (
                -- Caso 1: Temos informação do serviço - prestador é quem NÃO criou
                (srv.id_utilizador IS NOT NULL AND srv.id_utilizador != ? AND 
                 ((srv.id_utilizador = o.remetente_id AND o.destinatario_id = ?) OR 
                  (srv.id_utilizador = o.destinatario_id AND o.remetente_id = ?)))
                OR 
                -- Caso 2: Fallback - usar provider_id do escrow
                (srv.id_utilizador IS NULL AND e.provider_id = ?)
            )
            ORDER BY e.created_at DESC
        ");
        $stmt->bind_param("iiiiii", $provider_id, $provider_id, $provider_id, $provider_id, $provider_id, $provider_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Notifica o cliente sobre nova prova submetida
     */
    private function notifyClientAboutProof($escrow_id, $proof_id, $proof_title) {
        // Obter dados do escrow
        $stmt = $this->conn->prepare("
            SELECT e.client_id, e.provider_id,
                   o.remetente_id, o.destinatario_id,
                   s.id_utilizador as service_creator_id
            FROM escrow_transactions e
            JOIN ofertas o ON e.offer_id = o.id
            LEFT JOIN mensagens m ON (m.remetente_id = o.remetente_id AND m.destinatario_id = o.destinatario_id) 
                OR (m.remetente_id = o.destinatario_id AND m.destinatario_id = o.remetente_id)
            LEFT JOIN servicos s ON m.servico_id = s.id_servico
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $escrow_id);
        $stmt->execute();
        $escrow = $stmt->get_result()->fetch_assoc();
        
        if ($escrow) {
            // Determinar quem é o cliente (quem criou o serviço)
            $service_creator_id = $escrow['service_creator_id'];
            $offer_sender = $escrow['remetente_id'];
            $offer_receiver = $escrow['destinatario_id'];
            
            if ($service_creator_id) {
                $client_id = $service_creator_id;
                $provider_id = ($service_creator_id == $offer_sender) ? $offer_receiver : $offer_sender;
            } else {
                $client_id = $escrow['client_id'];
                $provider_id = $escrow['provider_id'];
            }
            
            // Enviar mensagem de notificação ao cliente
            $notification_message = "📋 Nova prova de entrega submetida: " . $proof_title . " - Acesse 'Provas de Entrega' para revisar.";
            $stmt = $this->conn->prepare("
                INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, data_envio, tipo) 
                VALUES (?, ?, ?, NOW(), 'sistema')
            ");
            $stmt->bind_param("iis", $provider_id, $client_id, $notification_message);
            $stmt->execute();
        }
    }
    
    /**
     * Processa liberação da primeira metade após primeira entrega
     */
    private function processFirstMilestoneRelease($escrow_id) {
        $stmt = $this->conn->prepare("
            SELECT total_amount, amount_released, status, provider_id 
            FROM escrow_transactions 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $escrow_id);
        $stmt->execute();
        $escrow = $stmt->get_result()->fetch_assoc();
        
        if ($escrow && $escrow['status'] === 'pending' && $escrow['amount_released'] == 0) {
            $first_release = $escrow['total_amount'] * 0.5; // 50%
            
            $stmt = $this->conn->prepare("
                UPDATE escrow_transactions 
                SET amount_released = ?, status = 'partial_released'
                WHERE id = ?
            ");
            $stmt->bind_param("di", $first_release, $escrow_id);
            $stmt->execute();
            
            $this->addToUserBalance($escrow["provider_id"], $first_release, 'service_payment', $escrow_id);
            
            // Notificar ambas as partes
            $this->sendEscrowNotification($escrow_id, 'first_milestone_released', $first_release);
        }
    }
    
    /**
     * Libera pagamento final após confirmação do cliente e adiciona ao saldo
     */
    public function releaseFinalPayment($escrow_id, $client_id) {
        try {
            $this->conn->begin_transaction();
            
            // Verificar autorização
            $stmt = $this->conn->prepare("
                SELECT e.total_amount, e.amount_released, e.client_id, e.provider_id
                FROM escrow_transactions e
                WHERE e.id = ? AND e.client_id = ? AND e.status = 'partial_released'
            ");
            $stmt->bind_param("ii", $escrow_id, $client_id);
            $stmt->execute();
            $escrow = $stmt->get_result()->fetch_assoc();
            
            if (!$escrow) {
                throw new Exception("Escrow não encontrado ou não autorizado");
            }
            
            $final_amount = $escrow['total_amount'] - $escrow['amount_released'];
            $provider_id = $escrow['provider_id'];
            $client_id = $escrow['client_id'];
            
            // Liberar pagamento final
            $stmt = $this->conn->prepare("
                UPDATE escrow_transactions 
                SET amount_released = total_amount, status = 'completed' 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $escrow_id);
            $stmt->execute();
            
            // Adicionar valor total ao saldo do prestador
            $this->addToUserBalance($provider_id, $escrow['total_amount'], 'service_payment', $escrow_id);
            
            // Atualizar reputação do prestador
            $this->updateProviderReputation($escrow_id, 'completed');
            
            // Log de segurança
            $this->logSecurityAction($client_id, 'payment', [
                'escrow_id' => $escrow_id,
                'action' => 'final_release',
                'amount' => $final_amount,
                'total_amount' => $escrow['total_amount'],
                'provider_id' => $provider_id
            ]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Adiciona valor ao saldo do usuário
     */
    private function addToUserBalance($user_id, $amount, $type, $reference_id = null) {
        // Verificar se a tabela de saldo existe, se não, criar
        $this->createUserBalanceTableIfNotExists();
        
        // Inserir transação de saldo
        $stmt = $this->conn->prepare("
            INSERT INTO user_balance_transactions 
            (user_id, amount, transaction_type, reference_id, description, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $description = $this->getTransactionDescription($type, $amount);
        $stmt->bind_param("idsss", $user_id, $amount, $type, $reference_id, $description);
        $stmt->execute();
        
        // Atualizar saldo total do usuário
        $stmt = $this->conn->prepare("
            INSERT INTO user_balances (user_id, total_balance, available_balance, last_updated) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
                total_balance = total_balance + ?, 
                available_balance = available_balance + ?, 
                last_updated = NOW()
        ");
        $stmt->bind_param("idddd", $user_id, $amount, $amount, $amount, $amount);
        $stmt->execute();
    }
    
    /**
     * Cria tabelas de saldo se não existirem
     */
    private function createUserBalanceTableIfNotExists() {
        // Tabela de saldos dos usuários
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS user_balances (
                user_id INT PRIMARY KEY,
                total_balance DECIMAL(10,2) DEFAULT 0.00,
                available_balance DECIMAL(10,2) DEFAULT 0.00,
                pending_balance DECIMAL(10,2) DEFAULT 0.00,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES utilizadores(id_utilizadores) ON DELETE CASCADE
            )
        ");
        
        // Tabela de transações de saldo
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS user_balance_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                transaction_type ENUM('service_payment', 'withdrawal', 'refund', 'bonus', 'penalty') NOT NULL,
                reference_id INT NULL,
                description TEXT,
                status ENUM('completed', 'pending', 'failed') DEFAULT 'completed',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES utilizadores(id_utilizadores) ON DELETE CASCADE,
                INDEX idx_user_date (user_id, created_at),
                INDEX idx_type (transaction_type),
                INDEX idx_status (status)
            )
        ");
    }
    
    /**
     * Gera descrição da transação
     */
    private function getTransactionDescription($type, $amount) {
        switch ($type) {
            case 'service_payment':
                return "Pagamento recebido por serviço prestado - €" . number_format($amount, 2);
            case 'withdrawal':
                return "Saque realizado - €" . number_format($amount, 2);
            case 'refund':
                return "Reembolso recebido - €" . number_format($amount, 2);
            case 'bonus':
                return "Bônus recebido - €" . number_format($amount, 2);
            case 'penalty':
                return "Penalidade aplicada - €" . number_format($amount, 2);
            default:
                return "Transação - €" . number_format($amount, 2);
        }
    }
    
    /**
     * Processa reembolso automático por não entrega
     */
    public function processAutoRefund($escrow_id) {
        try {
            $this->conn->begin_transaction();
            
            // Verificar se é elegível para reembolso automático
            $stmt = $this->conn->prepare("
                SELECT e.*, COUNT(dp.id) as delivery_count 
                FROM escrow_transactions e 
                LEFT JOIN delivery_proofs dp ON e.id = dp.escrow_id AND dp.status = 'approved'
                WHERE e.id = ? AND e.status = 'pending' AND e.auto_refund_date <= NOW()
                GROUP BY e.id
            ");
            $stmt->bind_param("i", $escrow_id);
            $stmt->execute();
            $escrow = $stmt->get_result()->fetch_assoc();
            
            if ($escrow && $escrow['delivery_count'] == 0) {
                // Processar reembolso
                $stmt = $this->conn->prepare("
                    UPDATE escrow_transactions 
                    SET status = 'refunded', amount_released = 0 
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $escrow_id);
                $stmt->execute();
                
                // Adicionar reembolso ao saldo do cliente
                $this->addToUserBalance($escrow['client_id'], $escrow['total_amount'], 'refund', $escrow_id);
                
                // Penalizar prestador
                $this->updateProviderReputation($escrow_id, 'auto_refunded');
                
                // Notificar partes
                $this->sendEscrowNotification($escrow_id, 'auto_refunded', $escrow['total_amount']);
                
                $this->conn->commit();
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Cria caso de disputa
     */
    public function createDispute($escrow_id, $complainant_id, $dispute_type, $title, $description, $evidence = []) {
        try {
            // Verificar se o escrow existe
            $stmt = $this->conn->prepare("
                SELECT client_id, provider_id 
                FROM escrow_transactions 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $escrow_id);
            $stmt->execute();
            $escrow = $stmt->get_result()->fetch_assoc();
            
            if (!$escrow) {
                throw new Exception("Escrow não encontrado");
            }
            
            $respondent_id = ($complainant_id == $escrow['client_id']) ? $escrow['provider_id'] : $escrow['client_id'];
            
            // Criar caso de disputa
            $stmt = $this->conn->prepare("
                INSERT INTO dispute_cases 
                (escrow_id, complainant_id, respondent_id, dispute_type, title, description, evidence) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $evidence_json = json_encode($evidence);
            $stmt->bind_param("iiissss", $escrow_id, $complainant_id, $respondent_id, $dispute_type, $title, $description, $evidence_json);
            $stmt->execute();
            
            $dispute_id = $this->conn->insert_id;
            
            // Atualizar status do escrow
            $stmt = $this->conn->prepare("
                UPDATE escrow_transactions 
                SET status = 'disputed' 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $escrow_id);
            $stmt->execute();
            
            // Log de segurança
            $this->logSecurityAction($complainant_id, 'dispute', [
                'escrow_id' => $escrow_id,
                'dispute_id' => $dispute_id,
                'type' => $dispute_type
            ]);
            
            return $dispute_id;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Obtém nível de verificação do usuário
     */
    public function getUserVerificationLevel($user_id) {
        $stmt = $this->conn->prepare("
            SELECT verification_level 
            FROM reputation_scores 
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result ? $result['verification_level'] : 'unverified';
    }
    
    /**
     * Atualiza reputação do prestador
     */
    private function updateProviderReputation($escrow_id, $outcome) {
        $stmt = $this->conn->prepare("
            SELECT provider_id FROM escrow_transactions WHERE id = ?
        ");
        $stmt->bind_param("i", $escrow_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $provider_id = $result['provider_id'];
            
            if ($outcome === 'completed') {
                $stmt = $this->conn->prepare("
                    UPDATE reputation_scores 
                    SET completed_services = completed_services + 1,
                        trust_score = LEAST(trust_score + 0.1, 5.0)
                    WHERE user_id = ?
                ");
            } else if ($outcome === 'auto_refunded') {
                $stmt = $this->conn->prepare("
                    UPDATE reputation_scores 
                    SET cancelled_services = cancelled_services + 1,
                        trust_score = GREATEST(trust_score - 0.5, 0.0)
                    WHERE user_id = ?
                ");
            }
            
            $stmt->bind_param("i", $provider_id);
            $stmt->execute();
        }
    }
    
    /**
     * Processa upload de arquivo
     */
    private function processFileUpload($file_data, $escrow_id) {
        $upload_dir = "uploads/delivery_proofs/{$escrow_id}/";
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($file_data['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        // Validar tipo de arquivo
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip', 'rar'];
        if (!in_array(strtolower($file_extension), $allowed_types)) {
            throw new Exception("Tipo de arquivo não permitido");
        }
        
        // Validar tamanho (máximo 10MB)
        if ($file_data['size'] > 10 * 1024 * 1024) {
            throw new Exception("Arquivo muito grande (máximo 10MB)");
        }
        
        if (move_uploaded_file($file_data['tmp_name'], $file_path)) {
            return [
                'path' => $file_path,
                'type' => $file_data['type'],
                'size' => $file_data['size']
            ];
        } else {
            throw new Exception("Erro ao fazer upload do arquivo");
        }
    }
    
    /**
     * Envia notificação sobre escrow
     */
    private function sendEscrowNotification($escrow_id, $type, $amount = null) {
        // Implementar sistema de notificações
        // Por agora, apenas log
        error_log("Escrow notification: {$type} for escrow {$escrow_id}, amount: {$amount}");
    }
    
    /**
     * Log de ações de segurança
     */
    private function logSecurityAction($user_id, $action_type, $details) {
        $stmt = $this->conn->prepare("
            INSERT INTO security_logs 
            (user_id, action_type, action_details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $details_json = json_encode($details);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt->bind_param("issss", $user_id, $action_type, $details_json, $ip_address, $user_agent);
        $stmt->execute();
    }
    
    /**
     * Executa verificações automáticas de reembolso
     */
    public function runAutoRefundCheck() {
        $stmt = $this->conn->prepare("
            SELECT id FROM escrow_transactions 
            WHERE status = 'pending' AND auto_refund_date <= NOW()
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $processed = 0;
        while ($row = $result->fetch_assoc()) {
            if ($this->processAutoRefund($row['id'])) {
                $processed++;
            }
        }
        
        return $processed;
    }
}
?>