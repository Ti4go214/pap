-- Tabelas para o sistema de configurações de email e notificações

-- Adicionar configurações de email à tabela config (se ainda não existirem)
INSERT IGNORE INTO config (param_key, param_value) VALUES
('smtp_host', '', 'Servidor SMTP para envio de emails'),
('smtp_port', '587', 'Porta do servidor SMTP'),
('smtp_username', '', 'Utilizador do SMTP'),
('smtp_password', '', 'Password do SMTP'),
('smtp_from', 'noreply@tstore.pt', 'Email remetente padrão'),
('smtp_from_name', 'TSTORE Sistema', 'Nome do remetente padrão'),
('email_alertas_stock', '0', 'Enviar alertas de stock baixo por email (0=Não, 1=Sim)'),
('email_resumo_diario', '0', 'Enviar resumo diário de vendas (0=Não, 1=Sim)'),
('email_novas_encomendas', '0', 'Notificar novas encomendas por email (0=Não, 1=Sim)'),
('email_admins', '', 'Emails dos administradores para notificações'),
('email_ultima_verificacao', '', 'Data da última verificação de alertas automáticos');

-- Tabela para registar tentativas de envio de email (para auditoria)
CREATE TABLE IF NOT EXISTS email_logs (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    para VARCHAR(255) NOT NULL,
    assunto VARCHAR(255) NOT NULL,
    mensagem TEXT,
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('enviado', 'falha', 'pendente') DEFAULT 'pendente',
    erro TEXT NULL,
    tentativas INT DEFAULT 1
);

-- Tabela para controlar quando os alertas foram enviados
CREATE TABLE IF NOT EXISTS alertas_enviados (
    id_alerta INT AUTO_INCREMENT PRIMARY KEY,
    tipo_alerta ENUM('stock_baixo', 'resumo_diario', 'nova_encomenda') NOT NULL,
    id_referencia INT NULL, -- ID do produto, encomenda, etc.
    data_envio DATE NOT NULL,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_alerta_tipo_data (tipo_alerta, data_envio)
);

-- Índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_email_logs_data ON email_logs(data_envio);
CREATE INDEX IF NOT EXISTS idx_email_logs_status ON email_logs(status);
CREATE INDEX IF NOT EXISTS idx_alertas_enviados_data ON alertas_enviados(data_envio);
CREATE INDEX IF NOT EXISTS idx_alertas_enviados_tipo ON alertas_enviados(tipo_alerta);
