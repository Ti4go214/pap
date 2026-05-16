<?php
include __DIR__ . '/../includes/db_connect.php';

echo "<h2>Iniciando Migração V2...</h2>";

// 1. Adicionar coluna imagem à tabela produtos
$sql = "ALTER TABLE produtos ADD COLUMN imagem VARCHAR(255) DEFAULT 'default_product.png' AFTER preco_unit";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>Coluna 'imagem' adicionada com sucesso!</p>";
} else {
    if ($conn->errno == 1060) {
        echo "<p style='color: orange;'>A coluna 'imagem' já existe.</p>";
    } else {
        echo "<p style='color: red;'>Erro ao adicionar coluna: " . $conn->error . "</p>";
    }
}

// 2. Criar a pasta de uploads se não existir
$uploadDir = __DIR__ . '/../assets/img/products/';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0777, true)) {
        echo "<p style='color: green;'>Pasta de uploads criada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>Erro ao criar pasta de uploads.</p>";
    }
} else {
    echo "<p style='color: orange;'>Pasta de uploads já existe.</p>";
}

echo "<h3>Migração Concluída!</h3>";
?>
<a href="../index.php">Voltar ao Dashboard</a>
