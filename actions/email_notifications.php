<?php
/**
 * @file email_notifications.php
 * @brief Sistema de notificações por email
 * @author Antigravity
 * @date 2026-05-12
 */

include __DIR__ . '/../includes/db_connect.php';

// Configurações de email (pode mover para tabela config depois)
$email_config = [
    'smtp_host' => $settings['smtp_host'] ?? 'localhost',
    'smtp_port' => $settings['smtp_port'] ?? 587,
    'smtp_username' => $settings['smtp_username'] ?? '',
    'smtp_password' => $settings['smtp_password'] ?? '',
    'smtp_from' => $settings['smtp_from'] ?? 'noreply@tstore.pt',
    'smtp_from_name' => $settings['smtp_from_name'] ?? 'TSTORE Sistema'
];

/**
 * Enviar email usando PHPMailer ou função mail() nativa
 */
function enviarEmail($para, $assunto, $mensagem, $html = false) {
    global $email_config;
    
    // Para simplificação, usar função mail() nativa
    // Em produção, deve usar PHPMailer ou outra biblioteca robusta
    $headers = [];
    
    if ($html) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    } else {
        $headers[] = 'Content-type: text/plain; charset=UTF-8';
    }
    
    $headers[] = 'From: ' . $email_config['smtp_from_name'] . ' <' . $email_config['smtp_from'] . '>';
    $headers[] = 'Reply-To: ' . $email_config['smtp_from'];
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    $headers_str = implode("\r\n", $headers);
    
    return mail($para, $assunto, $mensagem, $headers_str);
}

/**
 * Enviar alerta de stock baixo
 */
function enviarAlertaStockBaixo($produtos) {
    if (empty($produtos)) return false;
    
    $mensagem = '<html><body>';
    $mensagem .= '<h2 style="color: #ef4444;">⚠️ Alerta de Stock Baixo</h2>';
    $mensagem .= '<p>Os seguintes produtos atingiram o stock crítico:</p>';
    $mensagem .= '<table style="border-collapse: collapse; width: 100%; margin: 20px 0;">';
    $mensagem .= '<tr style="background: #f3f4f6; color: #333;">';
    $mensagem .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Produto</th>';
    $mensagem .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Stock Atual</th>';
    $mensagem .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Stock Mínimo</th>';
    $mensagem .= '</tr>';
    
    foreach ($produtos as $produto) {
        $mensagem .= '<tr>';
        $mensagem .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($produto['descricao']) . '</td>';
        $mensagem .= '<td style="padding: 10px; border: 1px solid #ddd; color: #ef4444; font-weight: bold;">' . $produto['quantidade'] . '</td>';
        $mensagem .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $produto['stock_minimo'] . '</td>';
        $mensagem .= '</tr>';
    }
    
    $mensagem .= '</table>';
    $mensagem .= '<p style="margin-top: 20px;">Por favor, reponha o stock o mais breve possível para evitar rupturas.</p>';
    $mensagem .= '<p><a href="http://seu-dominio.com/anti/stock.php" style="background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Stock</a></p>';
    $mensagem .= '</body></html>';
    
    return enviarEmail(
        $email_config['smtp_from'],
        '🚨 Alerta de Stock Baixo - TSTORE',
        $mensagem,
        true
    );
}

/**
 * Enviar resumo diário de vendas
 */
function enviarResumoDiarioVendas($data = null) {
    global $conn;
    
    if (!$data) {
        $data = date('Y-m-d', strtotime('yesterday'));
    }
    
    // Buscar vendas do dia
    $sql = "SELECT 
                COUNT(DISTINCT e.n_encomenda) as total_encomendas,
                COUNT(DISTINCT e.id_cliente) as total_clientes,
                SUM(el.quantidade * el.preco_unitario) as total_vendas,
                SUM(el.valor_iva) as total_iva
             FROM encomendas e
             JOIN encomendas_linhas el ON e.id_encomenda = el.id_encomenda
             WHERE DATE(e.data_encomenda) = '$data'
             AND e.estado IN ('entregue', 'enviado')";
    
    $result = $conn->query($sql);
    $dados = $result->fetch_assoc();
    
    if (!$dados || $dados['total_encomendas'] == 0) {
        return false; // Não enviar email se não houver vendas
    }
    
    $mensagem = '<html><body>';
    $mensagem .= '<h2 style="color: #10b981;">📊 Resumo Diário de Vendas</h2>';
    $mensagem .= '<p><strong>Data:</strong> ' . date('d/m/Y', strtotime($data)) . '</p>';
    
    $mensagem .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">';
    $mensagem .= '<div style="background: #f3f4f6; padding: 15px; border-radius: 8px;">';
    $mensagem .= '<h3 style="margin-top: 0;">Resumo do Dia</h3>';
    $mensagem .= '<p><strong>Encomendas:</strong> ' . $dados['total_encomendas'] . '</p>';
    $mensagem .= '<p><strong>Clientes:</strong> ' . $dados['total_clientes'] . '</p>';
    $mensagem .= '<p><strong>Total Vendas:</strong> €' . number_format($dados['total_vendas'], 2, ',', '.') . '</p>';
    $mensagem .= '<p><strong>Total IVA:</strong> €' . number_format($dados['total_iva'], 2, ',', '.') . '</p>';
    $mensagem .= '</div>';
    
    // Top 5 produtos mais vendidos
    $top_sql = "SELECT p.descricao, SUM(el.quantidade) as total_vendido
                  FROM encomendas_linhas el
                  JOIN produtos p ON el.id_produto = p.id_produto
                  JOIN encomendas e ON el.id_encomenda = e.id_encomenda
                  WHERE DATE(e.data_encomenda) = '$data'
                  AND e.estado IN ('entregue', 'enviado')
                  GROUP BY p.id_produto, p.descricao
                  ORDER BY total_vendido DESC
                  LIMIT 5";
    
    $top_result = $conn->query($top_sql);
    $mensagem .= '<div style="background: #f0fdf4; padding: 15px; border-radius: 8px;">';
    $mensagem .= '<h3 style="margin-top: 0;">Top 5 Produtos</h3>';
    
    if ($top_result && $top_result->num_rows > 0) {
        while ($produto = $top_result->fetch_assoc()) {
            $mensagem .= '<p>• ' . htmlspecialchars($produto['descricao']) . ': <strong>' . $produto['total_vendido'] . '</strong> unidades</p>';
        }
    }
    
    $mensagem .= '</div>';
    $mensagem .= '</div>';
    $mensagem .= '<p style="margin-top: 20px;"><a href="http://seu-dominio.com/anti/relatorios.php" style="background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Relatórios</a></p>';
    $mensagem .= '</body></html>';
    
    return enviarEmail(
        $email_config['smtp_from'],
        '📈 Resumo Diário de Vendas - ' . date('d/m/Y', strtotime($data)),
        $mensagem,
        true
    );
}

/**
 * Enviar alerta de nova encomenda
 */
function enviarAlertaNovaEncomenda($encomenda) {
    $mensagem = '<html><body>';
    $mensagem .= '<h2 style="color: #3b82f6;">🛒 Nova Encomenda Recebida</h2>';
    $mensagem .= '<p><strong>Número:</strong> ' . htmlspecialchars($encomenda['num_encomenda']) . '</p>';
    $mensagem .= '<p><strong>Cliente:</strong> ' . htmlspecialchars($encomenda['nome_cliente']) . '</p>';
    $mensagem .= '<p><strong>Data:</strong> ' . date('d/m/Y', strtotime($encomenda['data_encomenda'])) . '</p>';
    $mensagem .= '<p><strong>Estado:</strong> ' . ucfirst($encomenda['estado']) . '</p>';
    
    // Calcular total
    $total_sql = "SELECT SUM(quantidade * preco_unitario) as total 
                   FROM encomendas_linhas 
                   WHERE id_encomenda = " . $encomenda['id_encomenda'];
    $total_result = $conn->query($total_sql);
    $total = $total_result->fetch_assoc()['total'];
    
    $mensagem .= '<p><strong>Valor Total:</strong> €' . number_format($total, 2, ',', '.') . '</p>';
    $mensagem .= '<p style="margin-top: 20px;"><a href="http://seu-dominio.com/anti/encomendas.php" style="background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Encomenda</a></p>';
    $mensagem .= '</body></html>';
    
    return enviarEmail(
        $email_config['smtp_from'],
        '🛒 Nova Encomenda - ' . $encomenda['num_encomenda'],
        $mensagem,
        true
    );
}

/**
 * Verificar e enviar alertas automáticos
 */
function verificarAlertasAutomaticos() {
    global $conn;
    
    // 1. Alertas de stock baixo
    $stock_limit = $settings['stock_low_limit'] ?? 5;
    $sql = "SELECT p.*, c.descricao as categoria 
             FROM produtos p 
             LEFT JOIN categoria c ON p.id_categoria = c.id_categoria 
             WHERE p.quantidade <= $stock_limit
             AND p.id_produto NOT IN (
                 SELECT id_produto FROM notificacoes 
                 WHERE tipo = 'STOCK_BAIXO' 
                 AND DATE(data_criacao) = CURDATE()
             )";
    
    $result = $conn->query($sql);
    $produtos_criticos = [];
    
    if ($result && $result->num_rows > 0) {
        while ($produto = $result->fetch_assoc()) {
            $produtos_criticos[] = $produto;
        }
        
        if (!empty($produtos_criticos)) {
            enviarAlertaStockBaixo($produtos_criticos);
        }
    }
    
    // 2. Resumo diário (executar às 8h da manhã)
    $hora_atual = date('H');
    if ($hora_atual == '08') {
        enviarResumoDiarioVendas();
    }
}

// Executar verificação se chamado diretamente
if (isset($_GET['verificar_alertas'])) {
    verificarAlertasAutomaticos();
    echo json_encode(['success' => true, 'message' => 'Alertas verificados e enviados se necessário']);
}
?>
