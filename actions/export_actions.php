<?php
/**
 * @file export_actions.php
 * @brief Lógica para exportação de dados em formato CSV.
 * @author Antigravity
 * @date 2026-03-13
 */

include __DIR__ . '/../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    die("Acesso negado.");
}

$type = $_GET['type'] ?? '';

if ($type === 'stock') {
    $filename = "stock_tstore_" . date('Ymd_His') . ".csv";
    $query = "SELECT p.id_produto, c.descricao as categoria, p.quantidade, p.preco_unit, p.descricao 
              FROM produtos p 
              LEFT JOIN categoria c ON p.id_categoria = c.id_categoria";
              
    $subset = $_GET['subset'] ?? '';
    if (!empty($subset)) {
        $idsArray = explode(',', $subset);
        $cleanIds = array_filter(array_map('intval', $idsArray));
        if (!empty($cleanIds)) {
            $idsList = implode(',', $cleanIds);
            $query .= " WHERE p.id_produto IN ($idsList)";
        }
    }
    
    $header = ['ID', 'Categoria', 'Quantidade', 'Preco Unitario', 'Descricao'];
    exportCSV($conn, $query, $filename, $header);

} elseif ($type === 'movimentos') {
    $filename = "movimentos_tstore_" . date('Ymd_His') . ".csv";
    $query = "
        (SELECT n_cab as id_doc, 'ENTRADA' as tipo, cliente as entidade, data
         FROM ent_cab)
        UNION
        (SELECT n_cab as id_doc, 'SAIDA' as tipo, cliente as entidade, data
         FROM sai_cab)
        ORDER BY data DESC";

    $header = ['ID Doc', 'Tipo', 'Entidade', 'Data'];
    exportCSV($conn, $query, $filename, $header);

} elseif ($type === 'vendas') {
    // Exportação de vendas detalhadas com filtros
    $filename = "vendas_tstore_" . date('Ymd_His') . ".csv";

    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    $cliente = $_GET['cliente'] ?? '';

    $query = "
        SELECT s.n_cab as doc_id, 'SAIDA' as tipo, s.cliente, s.data,
               l.n_linha, p.descricao as produto, c.descricao as categoria,
               l.quantidade, l.preço as preco_unit, (l.quantidade * l.preço) as subtotal
        FROM sai_cab s
        JOIN linhas l ON s.n_cab = l.id
        LEFT JOIN produtos p ON l.id_produto = p.id_produto
        LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
        WHERE DATE(s.data) BETWEEN '$data_inicio' AND '$data_fim'";

    if (!empty($cliente)) {
        $cliente_escaped = $conn->real_escape_string($cliente);
        $query .= " AND s.cliente LIKE '%$cliente_escaped%'";
    }

    $query .= " ORDER BY s.data DESC, s.n_cab, l.n_linha";

    $header = ['Doc ID', 'Tipo', 'Cliente', 'Data', 'Linha', 'Produto', 'Categoria', 'Quantidade', 'Preço Unit.', 'Subtotal'];
    exportCSV($conn, $query, $filename, $header);

} elseif ($type === 'financeiro') {
    // Exportação de relatório financeiro consolidado
    $filename = "relatorio_financeiro_" . date('Ymd_His') . ".csv";

    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');

    $query = "
        SELECT
            DATE(h.data) as data,
            SUM(CASE WHEN h.tipo = 'ENTRADA' THEN l.quantidade * l.preço ELSE 0 END) as total_entradas,
            SUM(CASE WHEN h.tipo = 'SAIDA' THEN l.quantidade * l.preço ELSE 0 END) as total_saidas,
            SUM(CASE WHEN h.tipo = 'SAIDA' THEN l.quantidade * l.preço ELSE 0 END) -
            SUM(CASE WHEN h.tipo = 'ENTRADA' THEN l.quantidade * l.preço ELSE 0 END) as saldo
        FROM (
            SELECT n_cab, cliente, data, 'ENTRADA' as tipo FROM ent_cab
            UNION ALL
            SELECT n_cab, cliente, data, 'SAIDA' as tipo FROM sai_cab
        ) h
        JOIN linhas l ON h.n_cab = l.id
        WHERE DATE(h.data) BETWEEN '$data_inicio' AND '$data_fim'
        GROUP BY DATE(h.data)
        ORDER BY DATE(h.data) DESC";

    $header = ['Data', 'Total Entradas', 'Total Saídas', 'Saldo'];
    exportCSV($conn, $query, $filename, $header);

} elseif ($type === 'stock_critico') {
    // Exportação de stock crítico
    $filename = "stock_critico_" . date('Ymd_His') . ".csv";

    $stock_limit = $config['stock_low_limit'] ?? 5;

    $query = "
        SELECT p.id_produto, p.descricao, c.descricao as categoria,
               p.quantidade, p.preco_unit, (p.quantidade * p.preco_unit) as valor_total,
               CASE WHEN p.quantidade = 0 THEN 'SEM STOCK'
                    WHEN p.quantidade <= $stock_limit THEN 'CRÍTICO'
                    ELSE 'NORMAL' END as status
        FROM produtos p
        LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
        WHERE p.quantidade <= $stock_limit
        ORDER BY p.quantidade ASC";

    $header = ['ID', 'Produto', 'Categoria', 'Quantidade', 'Preço Unit.', 'Valor Total', 'Status'];
    exportCSV($conn, $query, $filename, $header);

} else {
    die("Tipo de exportação inválido.");
}

/**
 * Função para gerar e enviar o ficheiro CSV
 */
function exportCSV($conn, $query, $filename, $header) {
    $result = $conn->query($query);
    
    if (!$result) {
        die("Erro na consulta: " . $conn->error);
    }

    // Definir headers para download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para Excel reconhecer UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Cabeçalho
    fputcsv($output, $header, ';');

    // Dados
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit();
}
?>
