<?php
/**
 * @file migrar_cidade_localidade.php
 * @brief Script para renomear coluna cidade para localidade
 * @author Antigravity
 * @date 2026-05-05
 */

include 'includes/db_connect.php';

echo "<h2>Migração: cidade -> localidade</h2>";

// Tabela clientes
echo "<h3>1. Tabela clientes</h3>";
// Verificar se a coluna cidade ainda existe
$check_cidade = $conn->query("SHOW COLUMNS FROM clientes LIKE 'cidade'");
if ($check_cidade->num_rows > 0) {
    $sql1 = "ALTER TABLE clientes CHANGE COLUMN cidade localidade VARCHAR(100)";
    if ($conn->query($sql1)) {
        echo "✓ Coluna renomeada com sucesso na tabela clientes<br>";
    } else {
        echo "✗ Erro na tabela clientes: " . $conn->error . "<br>";
    }
} else {
    // Verificar se localidade já existe
    $check_localidade = $conn->query("SHOW COLUMNS FROM clientes LIKE 'localidade'");
    if ($check_localidade->num_rows > 0) {
        echo "✓ Coluna localidade já existe na tabela clientes<br>";
    } else {
        echo "✗ Coluna cidade não encontrada e localidade não existe na tabela clientes<br>";
    }
}

// Tabela fornecedores
echo "<h3>2. Tabela fornecedores</h3>";
// Verificar se a coluna cidade ainda existe
$check_cidade_forn = $conn->query("SHOW COLUMNS FROM fornecedores LIKE 'cidade'");
if ($check_cidade_forn->num_rows > 0) {
    $sql2 = "ALTER TABLE fornecedores CHANGE COLUMN cidade localidade VARCHAR(100)";
    if ($conn->query($sql2)) {
        echo "✓ Coluna renomeada com sucesso na tabela fornecedores<br>";
    } else {
        echo "✗ Erro na tabela fornecedores: " . $conn->error . "<br>";
    }
} else {
    // Verificar se localidade já existe
    $check_localidade_forn = $conn->query("SHOW COLUMNS FROM fornecedores LIKE 'localidade'");
    if ($check_localidade_forn->num_rows > 0) {
        echo "✓ Coluna localidade já existe na tabela fornecedores<br>";
    } else {
        echo "✗ Coluna cidade não encontrada e localidade não existe na tabela fornecedores<br>";
    }
}

// Tabela users
echo "<h3>3. Tabela users</h3>";
// Verificar se a coluna localidade já existe
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'localidade'");
if ($check_column->num_rows > 0) {
    echo "✓ Coluna localidade já existe na tabela users<br>";
} else {
    // Adicionar coluna localidade
    $sql3 = "ALTER TABLE users ADD COLUMN localidade VARCHAR(100) AFTER pais";
    if ($conn->query($sql3)) {
        echo "✓ Coluna localidade adicionada com sucesso na tabela users<br>";
    } else {
        echo "✗ Erro na tabela users: " . $conn->error . "<br>";
    }
}

echo "<h3>Migração concluída!</h3>";
echo "<a href='index.php'>Voltar ao Dashboard</a>";
?>
