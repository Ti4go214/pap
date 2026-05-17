<?php
include '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID não fornecido']);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT e.*, c.nome as nome_cliente 
        FROM encomendas e 
        LEFT JOIN clientes c ON e.id_cliente = c.id_cliente 
        WHERE e.id_encomenda = $id";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    $encomenda = $res->fetch_assoc();
    
    // Buscar produtos da encomenda
    $prod_sql = "SELECT el.*, p.descricao 
                 FROM encomendas_linhas el 
                 JOIN produtos p ON el.id_produto = p.id_produto 
                 WHERE el.id_encomenda = $id";
    $prod_res = $conn->query($prod_sql);
    
    $produtos = [];
    while ($p = $prod_res->fetch_assoc()) {
        $produtos[] = $p;
    }
    
    $encomenda['produtos'] = $produtos;
    
    echo json_encode($encomenda);
} else {
    echo json_encode(['error' => 'Encomenda não encontrada']);
}
?>
