<?php
include "includes/db_connect.php";
$erro = "";
$sucesso = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? '';
    $pwd = $_POST["pwd"] ?? '';

    if (!empty($username) && !empty($pwd)) {
        // 1. Verificar se o utilizador já existe
        $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erro = "Este utilizador já está registado.";
        } else {
            // 2. Criar hash da password e inserir
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (username, pwd) VALUES (?, ?)");
            $ins->bind_param("ss", $username, $hash);
            
            if ($ins->execute()) {
                $sucesso = "Conta criada com sucesso! Já pode fazer login.";
            } else {
                $erro = "Erro ao criar conta. Tente novamente.";
            }
            $ins->close();
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Registo • TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css"> </head>
<body>

<div class="login-card">
    <div class="logo"><span>T</span>STORE</div>
    <div class="subtitle">Criar Nova Conta</div>

    <?php if ($erro): ?>
        <div class="error-msg" style="color: #ef4444; margin-bottom: 15px;">
            <i class="fas fa-circle-exclamation"></i> <?= $erro ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="success-msg" style="color: #10b981; margin-bottom: 15px;">
            <i class="fas fa-check-circle"></i> <?= $sucesso ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>Escolha um Utilizador</label>
            <div class="input-container">
                <i class="fas fa-user"></i>
                <input type="text" name="username" class="form-input" required>
            </div>
        </div>

        <div class="form-group">
            <label>Palavra-passe</label>
            <div class="input-container">
                <i class="fas fa-lock"></i>
                <input type="password" name="pwd" class="form-input" required>
            </div>
        </div>

        <button type="submit" class="btn-login" style="background-color: #10b981;">
            Registar Conta
        </button>
    </form>

    <div class="register-link" style="margin-top: 20px;">
        Já tem conta? <a href="login.php">Voltar ao login</a>
    </div>
</div>

</body>
</html>