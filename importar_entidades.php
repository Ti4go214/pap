<?php
/**
 * @file importar_entidades.php
 * @brief Importar clientes e fornecedores dos movimentos existentes
 * @author Antigravity
 * @date 2026-04-28
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';

// Proteção - apenas admin
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    die("Acesso negado. Apenas administradores podem executar esta ação.");
}

echo "<!DOCTYPE html>
<html lang='pt'>
<head>
    <meta charset='UTF-8'>
    <title>Importar Entidades</title>
    <link rel='stylesheet' href='assets/css/styles.css'>
</head>
<body>
    <div class='background-overlay'></div>
    <div class='wrapper'>
        <main class='content-area'>
            <h2>Importar Entidades dos Movimentos</h2>";

// 1. Importar clientes únicos de sai_cab (saídas = vendas)
echo "<h3>1. Importando clientes de saídas (vendas)...</h3>";

$sql_clientes_sai = "SELECT DISTINCT cliente FROM sai_cab WHERE cliente != '0' AND cliente != '' AND cliente IS NOT NULL";
$result_clientes_sai = $conn->query($sql_clientes_sai);

$clientes_importados = 0;
if ($result_clientes_sai && $result_clientes_sai->num_rows > 0) {
    while ($row = $result_clientes_sai->fetch_assoc()) {
        $nome_cliente = $row['cliente'];
        
        // Verificar se já existe
        $check = $conn->prepare("SELECT id_cliente FROM clientes WHERE nome = ?");
        $check->bind_param("s", $nome_cliente);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows == 0) {
            // Inserir novo cliente
            $insert = $conn->prepare("INSERT INTO clientes (nome) VALUES (?)");
            $insert->bind_param("s", $nome_cliente);
            if ($insert->execute()) {
                $clientes_importados++;
                echo "<p style='color: green;'>✓ Cliente importado: " . htmlspecialchars($nome_cliente) . "</p>";
            }
        } else {
            echo "<p style='color: #aaa;'>- Cliente já existe: " . htmlspecialchars($nome_cliente) . "</p>";
        }
    }
} else {
    echo "<p>Sem clientes para importar de saídas.</p>";
}

echo "<p><strong>Total de clientes importados: $clientes_importados</strong></p>";

// 2. Importar fornecedores únicos de ent_cab (entradas = compras)
echo "<h3>2. Importando fornecedores de entradas (compras)...</h3>";

$sql_fornecedores_ent = "SELECT DISTINCT cliente FROM ent_cab WHERE cliente != '0' AND cliente != '' AND cliente IS NOT NULL";
$result_fornecedores_ent = $conn->query($sql_fornecedores_ent);

$fornecedores_importados = 0;
if ($result_fornecedores_ent && $result_fornecedores_ent->num_rows > 0) {
    while ($row = $result_fornecedores_ent->fetch_assoc()) {
        $nome_fornecedor = $row['cliente'];
        
        // Verificar se já existe
        $check = $conn->prepare("SELECT id_fornecedor FROM fornecedores WHERE nome = ?");
        $check->bind_param("s", $nome_fornecedor);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows == 0) {
            // Inserir novo fornecedor
            $insert = $conn->prepare("INSERT INTO fornecedores (nome) VALUES (?)");
            $insert->bind_param("s", $nome_fornecedor);
            if ($insert->execute()) {
                $fornecedores_importados++;
                echo "<p style='color: green;'>✓ Fornecedor importado: " . htmlspecialchars($nome_fornecedor) . "</p>";
            }
        } else {
            echo "<p style='color: #aaa;'>- Fornecedor já existe: " . htmlspecialchars($nome_fornecedor) . "</p>";
        }
    }
} else {
    echo "<p>Sem fornecedores para importar de entradas.</p>";
}

echo "<p><strong>Total de fornecedores importados: $fornecedores_importados</strong></p>";

// 3. Adicionar colunas de FK nas tabelas de movimentos
echo "<h3>3. Atualizando estrutura das tabelas de movimentos...</h3>";

// Adicionar id_cliente em sai_cab
$conn->query("ALTER TABLE sai_cab ADD COLUMN id_cliente INT NULL AFTER cliente");
echo "<p style='color: green;'>✓ Coluna id_cliente adicionada a sai_cab</p>";

// Adicionar id_fornecedor em ent_cab
$conn->query("ALTER TABLE ent_cab ADD COLUMN id_fornecedor INT NULL AFTER cliente");
echo "<p style='color: green;'>✓ Coluna id_fornecedor adicionada a ent_cab</p>";

// 4. Atualizar referências nos movimentos existentes
echo "<h3>4. Atualizando referências nos movimentos existentes...</h3>";

// Atualizar sai_cab com id_cliente
$update_sai = "UPDATE sai_cab s 
               LEFT JOIN clientes c ON s.cliente = c.nome 
               SET s.id_cliente = c.id_cliente 
               WHERE s.cliente != '0' AND s.cliente != '' AND s.cliente IS NOT NULL";
$conn->query($update_sai);
echo "<p style='color: green;'>✓ Referências de clientes atualizadas em sai_cab</p>";

// Atualizar ent_cab com id_fornecedor
$update_ent = "UPDATE ent_cab e 
               LEFT JOIN fornecedores f ON e.cliente = f.nome 
               SET e.id_fornecedor = f.id_fornecedor 
               WHERE e.cliente != '0' AND e.cliente != '' AND e.cliente IS NOT NULL";
$conn->query($update_ent);
echo "<p style='color: green;'>✓ Referências de fornecedores atualizadas em ent_cab</p>";

echo "<h3 style='color: green;'>✓ Importação concluída com sucesso!</h3>";
echo "<p><a href='gestao.php' class='nav-btn active' style='display: inline-block; margin-top: 20px;'>Voltar à Gestão</a></p>";
echo "</main></div></body></html>";
?>
