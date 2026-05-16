<?php
/**
 * @file exportar_pdf_entidade.php
 * @brief Exportar relatório de entidade para PDF
 * @author Antigravity
 * @date 2026-04-28
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';

if (!isset($_SESSION['user'])) {
    die("Acesso negado.");
}

$tipo_entidade = $_GET['tipo'] ?? 'cliente';
$entidade_id = $_GET['entidade'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-90 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

if (!$entidade_id) {
    die("Entidade não especificada.");
}

// Buscar dados da entidade
if ($tipo_entidade == 'cliente') {
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->bind_param("i", $entidade_id);
    $stmt->execute();
    $entidade_info = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("
        SELECT s.n_cab, s.data, 'SAÍDA' as tipo, l.id_produto, p.descricao, l.quantidade, l.preço,
               (l.quantidade * l.preço) as total
        FROM sai_cab s
        LEFT JOIN linhas l ON s.n_cab = l.id
        LEFT JOIN produtos p ON l.id_produto = p.id_produto
        WHERE s.id_cliente = ? AND s.data BETWEEN ? AND ?
        ORDER BY s.data DESC
    ");
    $stmt->bind_param("iss", $entidade_id, $data_inicio, $data_fim);
    $stmt->execute();
    $movimentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT s.n_cab) as total_documentos,
            COUNT(l.id) as total_linhas,
            SUM(l.quantidade) as total_quantidade,
            SUM(l.quantidade * l.preço) as total_valor
        FROM sai_cab s
        LEFT JOIN linhas l ON s.n_cab = l.id
        WHERE s.id_cliente = ? AND s.data BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $entidade_id, $data_inicio, $data_fim);
    $stmt->execute();
    $estatisticas = $stmt->get_result()->fetch_assoc();
    
    $tipo_label = "Cliente";
    
} else {
    $stmt = $conn->prepare("SELECT * FROM fornecedores WHERE id_fornecedor = ?");
    $stmt->bind_param("i", $entidade_id);
    $stmt->execute();
    $entidade_info = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("
        SELECT e.n_cab, e.data, 'ENTRADA' as tipo, l.id_produto, p.descricao, l.quantidade, l.preço,
               (l.quantidade * l.preço) as total
        FROM ent_cab e
        LEFT JOIN linhas l ON e.n_cab = l.id
        LEFT JOIN produtos p ON l.id_produto = p.id_produto
        WHERE e.id_fornecedor = ? AND e.data BETWEEN ? AND ?
        ORDER BY e.data DESC
    ");
    $stmt->bind_param("iss", $entidade_id, $data_inicio, $data_fim);
    $stmt->execute();
    $movimentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT e.n_cab) as total_documentos,
            COUNT(l.id) as total_linhas,
            SUM(l.quantidade) as total_quantidade,
            SUM(l.quantidade * l.preço) as total_valor
        FROM ent_cab e
        LEFT JOIN linhas l ON e.n_cab = l.id
        WHERE e.id_fornecedor = ? AND e.data BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $entidade_id, $data_inicio, $data_fim);
    $stmt->execute();
    $estatisticas = $stmt->get_result()->fetch_assoc();
    
    $tipo_label = "Fornecedor";
}

// Gerar HTML para PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório ' . $tipo_label . ' - ' . htmlspecialchars($entidade_info['nome']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #bc6ff1; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #bc6ff1; margin: 0; }
        .header p { color: #666; margin: 5px 0; }
        .entity-info { background: #f5f5f5; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .entity-info h2 { margin: 0 0 15px 0; color: #333; }
        .entity-info p { margin: 5px 0; color: #666; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: #f9f9f9; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #bc6ff1; }
        .stat-label { color: #666; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #bc6ff1; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .tipo-entrada { background: #10b981; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
        .tipo-saida { background: #ff4b2b; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
        .total-valor { font-weight: bold; color: #bc6ff1; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TSTORE - Sistema de Gestão</h1>
        <p>Relatório de ' . $tipo_label . '</p>
        <p>Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)) . '</p>
    </div>

    <div class="entity-info">
        <h2>' . htmlspecialchars($entidade_info['nome']) . '</h2>
        <p><strong>NIF:</strong> ' . htmlspecialchars($entidade_info['nif'] ?? '-') . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($entidade_info['email'] ?? '-') . '</p>
        <p><strong>Telefone:</strong> ' . htmlspecialchars($entidade_info['telefone'] ?? '-') . '</p>
        <p><strong>Morada:</strong> ' . htmlspecialchars($entidade_info['morada'] ?? '-') . '</p>
        <p><strong>Cidade:</strong> ' . htmlspecialchars($entidade_info['cidade'] ?? '-') . '</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value">' . number_format($estatisticas['total_documentos'] ?? 0, 0, ',', '.') . '</div>
            <div class="stat-label">Documentos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">' . number_format($estatisticas['total_linhas'] ?? 0, 0, ',', '.') . '</div>
            <div class="stat-label">Linhas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">' . number_format($estatisticas['total_quantidade'] ?? 0, 0, ',', '.') . '</div>
            <div class="stat-label">Unidades</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">' . number_format($estatisticas['total_valor'] ?? 0, 2, ',', '.') . '€</div>
            <div class="stat-label">Valor Total</div>
        </div>
    </div>

    <h3>Histórico de Movimentos</h3>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Documento</th>
                <th>Tipo</th>
                <th>Produto</th>
                <th>Qtd</th>
                <th>Preço</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($movimentos)) {
    foreach ($movimentos as $mov) {
        $html .= '
            <tr>
                <td>' . date('d/m/Y H:i', strtotime($mov['data'])) . '</td>
                <td>#' . $mov['n_cab'] . '</td>
                <td><span class="tipo-' . strtolower($mov['tipo']) . '">' . $mov['tipo'] . '</span></td>
                <td>' . htmlspecialchars($mov['descricao']) . '</td>
                <td>' . number_format($mov['quantidade'], 0, ',', '.') . '</td>
                <td>' . number_format($mov['preço'], 2, ',', '.') . '€</td>
                <td class="total-valor">' . number_format($mov['total'], 2, ',', '.') . '€</td>
            </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Sem movimentos no período selecionado.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Relatório gerado em ' . date('d/m/Y H:i') . ' | TSTORE - Sistema de Gestão</p>
    </div>
</body>
</html>';

// Converter HTML para PDF usando a função nativa do browser
echo $html;
?>
