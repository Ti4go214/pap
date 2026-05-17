<?php
include '../includes/db_connect.php';

echo "A recalcular totais de encomendas antigas...\n";

$encomendas = $conn->query("SELECT id_encomenda, desconto_percent FROM encomendas");

while ($enc = $encomendas->fetch_assoc()) {
    $id_enc = $enc['id_encomenda'];
    $desc_p = (float)$enc['desconto_percent'];
    
    // Buscar linhas e IVA
    $linhas = $conn->query("
        SELECT el.*, t.taxa as taxa_iva_atual 
        FROM encomendas_linhas el
        JOIN produtos p ON el.id_produto = p.id_produto
        LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
        LEFT JOIN iva_taxas t ON c.id_iva = t.id_taxa
        WHERE el.id_encomenda = $id_enc
    ");
    
    $total_bruto = 0;
    $total_iva = 0;
    
    while ($l = $linhas->fetch_assoc()) {
        $id_linha = $l['id_linha'];
        $base = $l['preco_unitario'] * $l['quantidade'];
        $taxa = $l['taxa_iva'] > 0 ? $l['taxa_iva'] : ($l['taxa_iva_atual'] ?? 0);
        $viva = $base * ($taxa / 100);
        
        // Atualizar a linha com a taxa e valor iva se estiverem a zero
        $conn->query("UPDATE encomendas_linhas SET taxa_iva = $taxa, valor_iva = $viva WHERE id_linha = $id_linha");
        
        $total_bruto += $base;
        $total_iva += $viva;
    }
    
    $total_com_iva = $total_bruto + $total_iva;
    $total_liquido = $total_com_iva * (1 - ($desc_p / 100));
    
    // Atualizar a encomenda principal
    $conn->query("UPDATE encomendas SET 
        total_bruto = $total_bruto, 
        total_iva = $total_iva, 
        total_liquido = $total_liquido 
        WHERE id_encomenda = $id_enc");
    
    echo "Encomenda #$id_enc atualizada.\n";
}

echo "Migração concluída!";
?>
