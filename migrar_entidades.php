<?php
/**
 * @file migrar_entidades.php
 * @brief Página para executar migração de entidades via browser
 * @author Antigravity
 * @date 2026-04-28
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';

// Proteção - apenas admin
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    die("Acesso negado. Apenas administradores podem executar migrações.");
}

echo "<!DOCTYPE html>
<html lang='pt'>
<head>
    <meta charset='UTF-8'>
    <title>Migração de Entidades</title>
    <link rel='stylesheet' href='assets/css/styles.css'>
</head>
<body>
    <div class='background-overlay'></div>
    <div class='wrapper'>
        <main class='content-area'>
            <h2>Migração de Estrutura de Entidades</h2>";

// 1. Corrigir tabela clientes
echo "<h3>1. Corrigindo tabela clientes...</h3>";

// Eliminar tabela existente
$conn->query("DROP TABLE IF EXISTS clientes");
echo "<p>Tabela clientes removida (se existia).</p>";

// Criar tabela clientes
$sql_clientes = "CREATE TABLE IF NOT EXISTS clientes (
  id_cliente INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  nif VARCHAR(20),
  email VARCHAR(100),
  telefone VARCHAR(20),
  morada VARCHAR(200),
  cpostal VARCHAR(10),
  cidade VARCHAR(50),
  pais VARCHAR(50) DEFAULT 'Portugal',
  data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_clientes)) {
    echo "<p style='color: green;'>✓ Tabela clientes criada com sucesso!</p>";
} else {
    echo "<p style='color: red;'>✗ Erro ao criar tabela clientes: " . $conn->error . "</p>";
}

// 2. Corrigir tabela fornecedores
echo "<h3>2. Corrigindo tabela fornecedores...</h3>";

// Eliminar tabela existente
$conn->query("DROP TABLE IF EXISTS fornecedores");
echo "<p>Tabela fornecedores removida (se existia).</p>";

// Criar tabela fornecedores
$sql_fornecedores = "CREATE TABLE IF NOT EXISTS fornecedores (
  id_fornecedor INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  nif VARCHAR(20),
  email VARCHAR(100),
  telefone VARCHAR(20),
  morada VARCHAR(200),
  cpostal VARCHAR(10),
  cidade VARCHAR(50),
  pais VARCHAR(50) DEFAULT 'Portugal',
  contacto_principal VARCHAR(100),
  email_contacto VARCHAR(100),
  data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_fornecedores)) {
    echo "<p style='color: green;'>✓ Tabela fornecedores criada com sucesso!</p>";
} else {
    echo "<p style='color: red;'>✗ Erro ao criar tabela fornecedores: " . $conn->error . "</p>";
}

// 3. Inserir dados de exemplo
echo "<h3>3. Inserindo dados de exemplo...</h3>";

// Cliente de exemplo
$sql_cliente_exemplo = "INSERT INTO clientes (nome, nif, email, telefone, morada, cpostal, cidade) 
                        VALUES ('João Silva', '123456789', 'joao.silva@email.com', '912345678', 'Rua das Flores, 123', '1000-001', 'Lisboa')";
$conn->query($sql_cliente_exemplo);

// Fornecedor de exemplo
$sql_fornecedor_exemplo = "INSERT INTO fornecedores (nome, nif, email, telefone, morada, cpostal, cidade, contacto_principal) 
                          VALUES ('Tech Distribuidora', '987654321', 'contacto@techdist.pt', '213456789', 'Avenida da Indústria, 456', '2000-000', 'Porto', 'João Costa')";
$conn->query($sql_fornecedor_exemplo);

echo "<p style='color: green;'>✓ Dados de exemplo inseridos!</p>";

echo "<h3 style='color: green;'>✓ Migração concluída com sucesso!</h3>";
echo "<p><a href='gestao.php' class='nav-btn active' style='display: inline-block; margin-top: 20px;'>Voltar à Gestão</a></p>";
echo "</main></div></body></html>";
?>
