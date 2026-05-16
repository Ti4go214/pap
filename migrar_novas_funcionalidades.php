<?php
/**
 * @file migrar_novas_funcionalidades.php
 * @brief Script para criar todas as tabelas das novas funcionalidades
 * @author Antigravity
 * @date 2026-05-12
 */

include 'includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

echo "<!DOCTYPE html>
<html lang='pt'>
<head>
    <meta charset='UTF-8'>
    <title>Migração de Novas Funcionalidades - TSTORE</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #1e0f32; 
            color: #fff; 
            margin: 0; 
            padding: 20px; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: rgba(255,255,255,0.1); 
            border-radius: 10px; 
            padding: 30px; 
        }
        .section { 
            margin-bottom: 30px; 
            padding: 20px; 
            background: rgba(188,111,241,0.1); 
            border-radius: 8px; 
        }
        .btn { 
            background: #3b82f6; 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 6px; 
            cursor: pointer; 
            margin: 10px 5px 10px 0; 
            font-size: 16px; 
        }
        .btn:hover { background: #2563eb; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .code { 
            background: #2d3748; 
            color: #fbbf24; 
            padding: 15px; 
            border-radius: 5px; 
            font-family: monospace; 
            font-size: 12px; 
            overflow-x: auto; 
        }
        .checklist { 
            text-align: left; 
            margin: 20px 0; 
        }
        .checklist li { 
            margin: 10px 0; 
            padding: 10px; 
            background: rgba(255,255,255,0.05); 
            border-radius: 5px; 
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1><i class='fas fa-database'></i> Migração de Novas Funcionalidades</h1>
        <p>Este script vai criar todas as tabelas necessárias para as novas funcionalidades do sistema TSTORE.</p>
        
        <div class='section'>
            <h2><i class='fas fa-cogs'></i> Tabelas a Criar</h2>
            <div class='checklist'>
                <li><strong>encomendas</strong> - Sistema de gestão de encomendas/vendas</li>
                <li><strong>encomendas_linhas</strong> - Linhas das encomendas</li>
                <li><strong>promocoes</strong> - Sistema de promoções e descontos</li>
                <li><strong>promocoes_categorias</strong> - Associação de promoções com categorias</li>
                <li><strong>promocoes_produtos</strong> - Associação de promoções com produtos</li>
                <li><strong>promocoes_utilizacoes</strong> - Utilização de códigos promocionais</li>
                <li><strong>iva_taxas</strong> - Taxas de IVA configuráveis</li>
                <li><strong>email_logs</strong> - Logs de envio de emails</li>
                <li><strong>alertas_enviados</strong> - Controlo de alertas enviados</li>
                <li><strong>config</strong> - Configurações de email e notificações</li>
            </div>
        </div>
        
        <div class='section'>
            <h2><i class='fas fa-play'></i> Executar Migração</h2>
            <p>Clique no botão abaixo para executar a migração. Este processo é irreversível.</p>
            
            <form method='POST'>
                <button type='submit' name='executar_migracao' class='btn'>
                    <i class='fas fa-rocket'></i> Executar Migração
                </button>
            </form>
        </div>
        
        <div class='section'>
            <h2><i class='fas fa-info-circle'></i> Instruções</h2>
            <ol>
                <li><strong>Backup:</strong> Faça backup da base de dados antes de executar</li>
                <li><strong>Execução:</strong> Clique em \"Executar Migração\"</li>
                <li><strong>Verificação:</strong> Verifique se todas as tabelas foram criadas sem erros</li>
                <li><strong>Configuração:</strong> Configure o SMTP em <strong>configuracoes_email.php</strong></li>
                <li><strong>IVA:</strong> Configure as taxas de IVA em <strong>configuracoes_iva.php</strong></li>
            </ol>
        </div>
    </div>";

// Se o formulário for submetido, executar a migração
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executar_migracao'])) {
    echo "<div class='container' style='margin-top: 30px;'>";
    
    try {
        $conn->begin_transaction();
        
        // 1. Criar tabelas de encomendas
        echo "<h3><i class='fas fa-shopping-cart'></i> A criar tabelas de encomendas...</h3>";
        
        $sql_encomendas = "CREATE TABLE IF NOT EXISTS encomendas (
            id_encomenda INT AUTO_INCREMENT PRIMARY KEY,
            num_encomenda VARCHAR(20) NOT NULL UNIQUE,
            id_cliente INT NOT NULL,
            data_encomenda DATE NOT NULL,
            estado ENUM('pendente', 'processamento', 'enviado', 'entregue', 'cancelado') DEFAULT 'pendente',
            observacoes TEXT,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE RESTRICT
        )";
        
        if ($conn->query($sql_encomendas)) {
            echo "<p class='success'>✓ Tabela encomendas criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela encomendas");
        }
        
        $sql_encomendas_linhas = "CREATE TABLE IF NOT EXISTS encomendas_linhas (
            id_linha INT AUTO_INCREMENT PRIMARY KEY,
            id_encomenda INT NOT NULL,
            id_produto INT NOT NULL,
            quantidade INT NOT NULL,
            preco_unitario DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (id_encomenda) REFERENCES encomendas(id_encomenda) ON DELETE CASCADE,
            FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT
        )";
        
        if ($conn->query($sql_encomendas_linhas)) {
            echo "<p class='success'>✓ Tabela encomendas_linhas criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela encomendas_linhas");
        }
        
        // 2. Criar tabelas de promoções
        echo "<h3><i class='fas fa-tags'></i> A criar tabelas de promoções...</h3>";
        
        $sql_promocoes = "CREATE TABLE IF NOT EXISTS promocoes (
            id_promocao INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            tipo ENUM('percentagem', 'fixo') NOT NULL,
            valor_desconto DECIMAL(10,2) NOT NULL,
            data_inicio DATE NOT NULL,
            data_fim DATE NOT NULL,
            codigo_promocional VARCHAR(20) NULL,
            minimo_compra DECIMAL(10,2) DEFAULT 0,
            utilizacoes_maximas INT DEFAULT 0,
            ativo TINYINT(1) DEFAULT 1,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql_promocoes)) {
            echo "<p class='success'>✓ Tabela promocoes criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela promocoes");
        }
        
        $sql_promocoes_categorias = "CREATE TABLE IF NOT EXISTS promocoes_categorias (
            id_promocao INT NOT NULL,
            id_categoria INT NOT NULL,
            PRIMARY KEY (id_promocao, id_categoria),
            FOREIGN KEY (id_promocao) REFERENCES promocoes(id_promocao) ON DELETE CASCADE,
            FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria) ON DELETE CASCADE
        )";
        
        if ($conn->query($sql_promocoes_categorias)) {
            echo "<p class='success'>✓ Tabela promocoes_categorias criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela promocoes_categorias");
        }
        
        $sql_promocoes_produtos = "CREATE TABLE IF NOT EXISTS promocoes_produtos (
            id_promocao INT NOT NULL,
            id_produto INT NOT NULL,
            PRIMARY KEY (id_promocao, id_produto),
            FOREIGN KEY (id_promocao) REFERENCES promocoes(id_promocao) ON DELETE CASCADE,
            FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE CASCADE
        )";
        
        if ($conn->query($sql_promocoes_produtos)) {
            echo "<p class='success'>✓ Tabela promocoes_produtos criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela promocoes_produtos");
        }
        
        $sql_promocoes_utilizacoes = "CREATE TABLE IF NOT EXISTS promocoes_utilizacoes (
            id_utilizacao INT AUTO_INCREMENT PRIMARY KEY,
            id_promocao INT NOT NULL,
            id_cliente INT NULL,
            id_encomenda INT NULL,
            data_utilizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            valor_desconto_aplicado DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (id_promocao) REFERENCES promocoes(id_promocao) ON DELETE CASCADE,
            FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL,
            FOREIGN KEY (id_encomenda) REFERENCES encomendas(id_encomenda) ON DELETE SET NULL
        )";
        
        if ($conn->query($sql_promocoes_utilizacoes)) {
            echo "<p class='success'>✓ Tabela promocoes_utilizacoes criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela promocoes_utilizacoes");
        }
        
        // 3. Criar tabelas de IVA
        echo "<h3><i class='fas fa-percentage'></i> A criar tabelas de IVA...</h3>";
        
        $sql_iva_taxas = "CREATE TABLE IF NOT EXISTS iva_taxas (
            id_taxa INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(50) NOT NULL,
            taxa DECIMAL(5,2) NOT NULL,
            descricao TEXT,
            padrao TINYINT(1) DEFAULT 0,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql_iva_taxas)) {
            echo "<p class='success'>✓ Tabela iva_taxas criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela iva_taxas");
        }
        
        // 4. Criar tabelas de email
        echo "<h3><i class='fas fa-envelope'></i> A criar tabelas de email...</h3>";
        
        $sql_email_logs = "CREATE TABLE IF NOT EXISTS email_logs (
            id_log INT AUTO_INCREMENT PRIMARY KEY,
            para VARCHAR(255) NOT NULL,
            assunto VARCHAR(255) NOT NULL,
            mensagem TEXT,
            data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('enviado', 'falha', 'pendente') DEFAULT 'pendente',
            erro TEXT NULL,
            tentativas INT DEFAULT 1
        )";
        
        if ($conn->query($sql_email_logs)) {
            echo "<p class='success'>✓ Tabela email_logs criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela email_logs");
        }
        
        $sql_alertas_enviados = "CREATE TABLE IF NOT EXISTS alertas_enviados (
            id_alerta INT AUTO_INCREMENT PRIMARY KEY,
            tipo_alerta ENUM('stock_baixo', 'resumo_diario', 'nova_encomenda') NOT NULL,
            id_referencia INT NULL,
            data_envio DATE NOT NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_alerta_tipo_data (tipo_alerta, data_envio)
        )";
        
        if ($conn->query($sql_alertas_enviados)) {
            echo "<p class='success'>✓ Tabela alertas_enviados criada</p>";
        } else {
            throw new Exception("Erro ao criar tabela alertas_enviados");
        }
        
        // 5. Adicionar colunas de IVA às tabelas existentes
        echo "<h3><i class='fas fa-edit'></i> A adicionar colunas de IVA...</h3>";
        
        // Verificar e adicionar colunas à tabela categoria
        $check_categoria = $conn->query("SHOW COLUMNS FROM categoria LIKE 'id_iva'");
        if ($check_categoria->num_rows == 0) {
            $alter_categoria = "ALTER TABLE categoria ADD COLUMN id_iva INT NULL AFTER id_categoria";
            if ($conn->query($alter_categoria)) {
                echo "<p class='success'>✓ Coluna id_iva adicionada à tabela categoria</p>";
            } else {
                echo "<p class='error'>✗ Erro ao adicionar coluna id_iva à tabela categoria</p>";
            }
        } else {
            echo "<p class='error'>⚠ Coluna id_iva já existe na tabela categoria</p>";
        }
        
        // Verificar e adicionar colunas à tabela produtos
        $check_produtos1 = $conn->query("SHOW COLUMNS FROM produtos LIKE 'preco_com_iva'");
        if ($check_produtos1->num_rows == 0) {
            $alter_produtos1 = "ALTER TABLE produtos ADD COLUMN preco_com_iva DECIMAL(10,2) DEFAULT 0 AFTER preco_unit";
            if ($conn->query($alter_produtos1)) {
                echo "<p class='success'>✓ Coluna preco_com_iva adicionada à tabela produtos</p>";
            } else {
                echo "<p class='error'>✗ Erro ao adicionar coluna preco_com_iva à tabela produtos</p>";
            }
        } else {
            echo "<p class='error'>⚠ Coluna preco_com_iva já existe na tabela produtos</p>";
        }
        
        $check_produtos2 = $conn->query("SHOW COLUMNS FROM produtos LIKE 'id_iva'");
        if ($check_produtos2->num_rows == 0) {
            $alter_produtos2 = "ALTER TABLE produtos ADD COLUMN id_iva INT NULL AFTER id_categoria";
            if ($conn->query($alter_produtos2)) {
                echo "<p class='success'>✓ Coluna id_iva adicionada à tabela produtos</p>";
            } else {
                echo "<p class='error'>✗ Erro ao adicionar coluna id_iva à tabela produtos</p>";
            }
        } else {
            echo "<p class='error'>⚠ Coluna id_iva já existe na tabela produtos</p>";
        }
        
        // Verificar e adicionar colunas à tabela linhas
        $check_linhas1 = $conn->query("SHOW COLUMNS FROM linhas LIKE 'valor_iva'");
        if ($check_linhas1->num_rows == 0) {
            $alter_linhas1 = "ALTER TABLE linhas ADD COLUMN valor_iva DECIMAL(10,2) DEFAULT 0 AFTER preço";
            if ($conn->query($alter_linhas1)) {
                echo "<p class='success'>✓ Coluna valor_iva adicionada à tabela linhas</p>";
            } else {
                echo "<p class='error'>✗ Erro ao adicionar coluna valor_iva à tabela linhas</p>";
            }
        } else {
            echo "<p class='error'>⚠ Coluna valor_iva já existe na tabela linhas</p>";
        }
        
        $check_linhas2 = $conn->query("SHOW COLUMNS FROM linhas LIKE 'valor_total_iva'");
        if ($check_linhas2->num_rows == 0) {
            $alter_linhas2 = "ALTER TABLE linhas ADD COLUMN valor_total_iva DECIMAL(10,2) DEFAULT 0 AFTER valor_iva";
            if ($conn->query($alter_linhas2)) {
                echo "<p class='success'>✓ Coluna valor_total_iva adicionada à tabela linhas</p>";
            } else {
                echo "<p class='error'>✗ Erro ao adicionar coluna valor_total_iva à tabela linhas</p>";
            }
        } else {
            echo "<p class='error'>⚠ Coluna valor_total_iva já existe na tabela linhas</p>";
        }
        
        // Verificar e adicionar colunas à tabela encomendas_linhas
        $check_encomendas1 = $conn->query("SHOW COLUMNS FROM encomendas_linhas LIKE 'valor_iva'");
        if ($check_encomendas1->num_rows == 0) {
            $alter_encomendas1 = "ALTER TABLE encomendas_linhas ADD COLUMN valor_iva DECIMAL(10,2) DEFAULT 0 AFTER preco_unitario";
            if ($conn->query($alter_encomendas1)) {
                echo "<p class='success'>✓ Coluna valor_iva adicionada à tabela encomendas_linhas</p>";
            } else {
                echo "<p class='error'>✗ Erro ao adicionar coluna valor_iva à tabela encomendas_linhas</p>";
            }
        } else {
            echo "<p class='error'>⚠ Coluna valor_iva já existe na tabela encomendas_linhas</p>";
        }
        
        $check_encomendas2 = $conn->query("SHOW COLUMNS FROM encomendas_linhas LIKE 'valor_total_iva'");
        if ($check_encomendas2->num_rows == 0) {
            $alter_encomendas2 = "ALTER TABLE encomendas_linhas ADD COLUMN valor_total_iva DECIMAL(10,2) DEFAULT 0 AFTER valor_iva";
            if ($conn->query($alter_encomendas2)) {
                echo "<p class='success'>✓ Coluna valor_total_iva adicionada à tabela encomendas_linhas</p>";
            } else {
                echo "<p class='error'>✗ Erro ao adicionar coluna valor_total_iva à tabela encomendas_linhas</p>";
            }
        } else {
            echo "<p class='error'>⚠ Coluna valor_total_iva já existe na tabela encomendas_linhas</p>";
        }
        
        // 6. Inserir taxas de IVA padrão
        echo "<h3><i class='fas fa-calculator'></i> A inserir taxas de IVA padrão...</h3>";
        
        $iva_padrao = [
            "INSERT IGNORE INTO iva_taxas (nome, taxa, descricao, padrao) VALUES
            ('Normal', 23.00, 'Taxa normal de IVA - Portugal Continental', 1)",
            "('Intermedio', 13.00, 'Taxa intermedia de IVA', 0)",
            "('Reduzido', 6.00, 'Taxa reduzida de IVA', 0)",
            "('Isento', 0.00, 'Isento de IVA', 0)"
        ];
        
        foreach ($iva_padrao as $sql) {
            if ($conn->query($sql)) {
                echo "<p class='success'>✓ Taxa de IVA inserida</p>";
            } else {
                throw new Exception("Erro ao inserir taxa de IVA");
            }
        }
        
        // 7. Inserir configurações de email
        echo "<h3><i class='fas fa-cog'></i> A inserir configurações de email...</h3>";
        
        $config_email = [
            "INSERT IGNORE INTO config (param_key, param_value) VALUES
            ('smtp_host', '', 'Servidor SMTP para envio de emails')",
            "('smtp_port', '587', 'Porta do servidor SMTP')",
            "('smtp_username', '', 'Utilizador do SMTP')",
            "('smtp_password', '', 'Password do SMTP')",
            "('smtp_from', 'noreply@tstore.pt', 'Email remetente padrão')",
            "('smtp_from_name', 'TSTORE Sistema', 'Nome do remetente padrão')",
            "('email_alertas_stock', '0', 'Enviar alertas de stock baixo por email (0=Não, 1=Sim)')",
            "('email_resumo_diario', '0', 'Enviar resumo diário de vendas (0=Não, 1=Sim)')",
            "('email_novas_encomendas', '0', 'Notificar novas encomendas por email (0=Não, 1=Sim)')",
            "('email_admins', '', 'Emails dos administradores para notificações')",
            "('email_ultima_verificacao', '', 'Data da última verificação de alertas automáticos')"
        ];
        
        foreach ($config_email as $sql) {
            if ($conn->query($sql)) {
                echo "<p class='success'>✓ Configuração de email inserida</p>";
            } else {
                throw new Exception("Erro ao inserir configuração de email");
            }
        }
        
        $conn->commit();
        
        echo "<div class='section' style='background: rgba(16, 185, 129, 0.1); padding: 20px; border-radius: 8px;'>";
        echo "<h2 class='success'><i class='fas fa-check-circle'></i> Migração Concluída com Sucesso!</h2>";
        echo "<p>Todas as tabelas e configurações foram criadas com sucesso.</p>";
        echo "<p><strong>Próximos passos:</strong></p>";
        echo "<ol>";
        echo "<li>Configure o servidor SMTP em <a href='configuracoes_email.php'>configuracoes_email.php</a></li>";
        echo "<li>Configure as taxas de IVA em <a href='configuracoes_iva.php'>configuracoes_iva.php</a></li>";
        echo "<li>Teste as notificações por email</li>";
        echo "<li>Use as novas funcionalidades: <a href='encomendas.php'>Gestão de Encomendas</a>, <a href='promocoes.php'>Promoções</a></li>";
        echo "</ol>";
        echo "</div>";
        
        // Registar log da migração
        registarLog($_SESSION['id_user'], 'MIGRACAO_NOVAS_FUNCIONALIDADES', 'Criadas tabelas para encomendas, promoções, IVA e email');
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<div class='section' style='background: rgba(239, 68, 68, 0.1); padding: 20px; border-radius: 8px;'>";
        echo "<h2 class='error'><i class='fas fa-exclamation-triangle'></i> Erro na Migração</h2>";
        echo "<p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>A migração foi revertida. Nenhuma alteração foi aplicada.</p>";
        echo "</div>";
    }
    
    echo "</div>";
}
?>

</body>
</html>";
