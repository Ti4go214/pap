<?php
include '../includes/db_connect.php';

// Adicionar colunas de totais à tabela encomendas
$conn->query("ALTER TABLE encomendas ADD COLUMN total_bruto DECIMAL(10,2) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE encomendas ADD COLUMN total_iva DECIMAL(10,2) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE encomendas ADD COLUMN total_liquido DECIMAL(10,2) NOT NULL DEFAULT 0");

echo "Colunas de totais adicionadas com sucesso!";
?>
