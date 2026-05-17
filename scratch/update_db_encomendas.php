<?php
include '../includes/db_connect.php';

// Atualizar encomendas_linhas
$conn->query("ALTER TABLE encomendas_linhas ADD COLUMN taxa_iva DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER preco_unitario");
$conn->query("ALTER TABLE encomendas_linhas ADD COLUMN valor_iva DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER taxa_iva");

// Atualizar encomendas (para guardar totais cacheados)
$conn->query("ALTER TABLE encomendas ADD COLUMN total_bruto DECIMAL(10,2) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE encomendas ADD COLUMN total_iva DECIMAL(10,2) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE encomendas ADD COLUMN total_liquido DECIMAL(10,2) NOT NULL DEFAULT 0");

echo "Estrutura de Base de Dados atualizada com sucesso!";
?>
