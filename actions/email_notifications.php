<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

include_once __DIR__ . '/../includes/db_connect.php';

// Configurações de email
$email_config = [
    'smtp_host' => $settings['smtp_host'] ?? '',
    'smtp_port' => $settings['smtp_port'] ?? 587,
    'smtp_username' => $settings['smtp_username'] ?? '',
    'smtp_password' => $settings['smtp_password'] ?? '',
    'smtp_from' => $settings['smtp_from'] ?? '',
    'smtp_from_name' => $settings['smtp_from_name'] ?? 'TSTORE Sistema'
];

// URL base do sistema para links nos emails
$base_url = rtrim($settings['site_url'] ?? 'http://localhost/pap', '/');

/**
 * Enviar email usando PHPMailer via SMTP
 */
function enviarEmail($para, $assunto, $mensagem, $html = false) {
    global $email_config;
    
    // Validar se temos as configurações básicas
    if (empty($email_config['smtp_host']) || empty($email_config['smtp_username'])) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Configurações do Servidor
        $mail->isSMTP();
        $mail->Host       = $email_config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $email_config['smtp_username'];
        $mail->Password   = $email_config['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $email_config['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        // Destinatários
        $mail->setFrom($email_config['smtp_from'], $email_config['smtp_from_name']);
        
        // Suporte para múltiplos destinatários separados por vírgula
        if (strpos($para, ',') !== false) {
            $emails = explode(',', $para);
            foreach ($emails as $email) {
                $mail->addAddress(trim($email));
            }
        } else {
            $mail->addAddress($para);
        }

        // Conteúdo
        $mail->isHTML($html);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagem;
        
        if (!$html) {
            $mail->AltBody = strip_tags($mensagem);
        }

        return $mail->send();
    } catch (Exception $e) {
        error_log("Erro ao enviar email: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Enviar alerta de stock baixo
 */
function enviarAlertaStockBaixo($produtos) {
    global $email_config;
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
    $mensagem .= '<p><a href="' . $base_url . '/stock.php" style="background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Stock</a></p>';
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
    global $conn, $email_config;
    
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
    $mensagem .= '<p style="margin-top: 20px;"><a href="' . $base_url . '/relatorios.php" style="background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Relatórios</a></p>';
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
    global $conn, $email_config;
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
    $mensagem .= '<p style="margin-top: 20px;"><a href="' . $base_url . '/encomendas.php" style="background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Encomenda</a></p>';
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
    global $conn, $settings;
    
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

/**
 * Enviar email ao cliente quando a encomenda é entregue
 */
function enviarEmailStatusEntregue($id_encomenda) {
    global $conn, $email_config;
    
    $sql = "SELECT e.*, c.nome as nome_cliente, c.email as email_cliente 
            FROM encomendas e 
            JOIN clientes c ON e.id_cliente = c.id_cliente 
            WHERE e.id_encomenda = " . (int)$id_encomenda;
    $res = $conn->query($sql);
    
    if ($res && $res->num_rows > 0) {
        $dados = $res->fetch_assoc();
        $email_cliente = $dados['email_cliente'];
        
        if (empty($email_cliente)) return false;

        $mensagem = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $mensagem .= '<div style="max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 10px; overflow: hidden;">';
        $mensagem .= '<div style="background: #bc6ff1; padding: 20px; text-align: center; color: white;">';
        $mensagem .= '<h1 style="margin: 0;">TSTORE</h1>';
        $mensagem .= '</div>';
        $mensagem .= '<div style="padding: 30px;">';
        $mensagem .= '<h2>Olá, ' . htmlspecialchars($dados['nome_cliente']) . '! 👋</h2>';
        $mensagem .= '<p>Temos o prazer de informar que a sua encomenda <strong>#' . $dados['num_encomenda'] . '</strong> foi entregue com sucesso.</p>';
        $mensagem .= '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $mensagem .= '<p style="margin: 0;"><strong>Data da Entrega:</strong> ' . date('d/m/Y H:i') . '</p>';
        $mensagem .= '<p style="margin: 5px 0 0 0;"><strong>Estado:</strong> ✅ Entregue</p>';
        $mensagem .= '</div>';
        $mensagem .= '<p>Esperamos que esteja satisfeito com os seus produtos. Se tiver alguma dúvida, não hesite em contactar-nos.</p>';
        $mensagem .= '<p>Obrigado por escolher a <strong>TSTORE</strong>!</p>';
        $mensagem .= '</div>';
        $mensagem .= '<div style="background: #f1f1f1; padding: 15px; text-align: center; font-size: 0.8rem; color: #777;">';
        $mensagem .= 'Este é um email automático, por favor não responda.';
        $mensagem .= '</div>';
        $mensagem .= '</div>';
        $mensagem .= '</body></html>';

        return enviarEmail($email_cliente, '📦 A sua encomenda foi entregue! - ' . $dados['num_encomenda'], $mensagem, true);
    }
    return false;
}

// Executar verificação se chamado diretamente
if (isset($_GET['verificar_alertas'])) {
    verificarAlertasAutomaticos();
    echo json_encode(['success' => true, 'message' => 'Alertas verificados e enviados se necessário']);
}
?>
