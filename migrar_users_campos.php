<?php
/**
 * @file migrar_users_campos.php
 * @brief Adicionar campos adicionais à tabela users
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
    <title>Migração de Campos Users</title>
    <link rel='stylesheet' href='assets/css/styles.css'>
</head>
<body>
    <div class='background-overlay'></div>
    <div class='wrapper'>
        <main class='content-area'>
            <h2>Adicionar Campos à Tabela Users</h3>";

// Adicionar campos à tabela users
$campos = [
    "nome VARCHAR(100)",
    "morada VARCHAR(200)",
    "postal VARCHAR(10)",
    "nascimento DATE",
    "nif VARCHAR(20)",
    "contacto VARCHAR(20)",
    "pais VARCHAR(50) DEFAULT 'Portugal'"
];

foreach ($campos as $campo) {
    $campo_nome = explode(' ', $campo)[0];
    
    // Verificar se o campo já existe
    $check = $conn->query("SHOW COLUMNS FROM users LIKE '$campo_nome'");
    
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE users ADD COLUMN $campo";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ Campo '$campo_nome' adicionado com sucesso!</p>";
        } else {
            echo "<p style='color: red;'>✗ Erro ao adicionar campo '$campo_nome': " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: #aaa;'>- Campo '$campo_nome' já existe.</p>";
    }
}

echo "<h3 style='color: green;'>✓ Migração concluída!</h3>";
echo "<p><a href='utilizadores.php' class='nav-btn active' style='display: inline-block; margin-top: 20px;'>Voltar à Gestão de Utilizadores</a></p>";
echo "</main></div></body></html>";
?>
