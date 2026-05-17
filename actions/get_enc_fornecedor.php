<?php
/**
 * @file get_enc_fornecedor.php
 * @brief AJAX - Retorna dados de uma encomenda a fornecedor para edição
 */
include '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Acesso negado']);
    exit();
}

$id = (int)($_GET['id'] ?? 0);

$sql = "SELECT ef.*, f.nome as nome_fornecedor 
        FROM encomendas_fornecedores ef 
        LEFT JOIN fornecedores f ON ef.id_fornecedor = f.id_fornecedor 
        WHERE ef.id_enc_fornecedor = $id";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    $enc = $res->fetch_assoc();

    $prod_sql = "SELECT efl.*, p.descricao, p.preco_unit
                 FROM encomendas_fornecedores_linhas efl
                 JOIN produtos p ON efl.id_produto = p.id_produto
                 WHERE efl.id_enc_fornecedor = $id";
    $prod_res = $conn->query($prod_sql);
    $produtos = [];
    while ($p = $prod_res->fetch_assoc()) {
        $produtos[] = $p;
    }
    $enc['produtos'] = $produtos;
    echo json_encode($enc);
} else {
    echo json_encode(['error' => 'Encomenda não encontrada']);
}
?>
