<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Alterado para include conforme pedido
include "includes/db_connect.php";
$erro = "";
/*
if (isset($_SESSION["user"])) {
    header("Location: ../pap2/index.php");
    exit;
}
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? '';
    $pwd = $_POST["pwd"] ?? '';

    // Utilizando a variável $conn do seu ficheiro db.php
    $stmt = $conn->prepare("SELECT id_user, username, pwd, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($pwd, $user["pwd"])) {
        $_SESSION["id_user"] = $user["id_user"]; // Importante para a auditoria
        $_SESSION["user"] = $user["username"];
        $_SESSION["role"] = $user["role"] ?? 1;
        header("Location: index.php");
        exit;
    }
    $erro = "Utilizador ou password incorretos";
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login • TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>
    <!-- Background Animated Elements -->
    <div class="bg-blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <div class="login-card">
        <div class="logo"><span>T</span>STORE</div>
        <div class="subtitle">Gestão de Inventário & Vendas</div>

        <?php if ($erro): ?>
            <div class="error-msg">
                <i class="fas fa-circle-exclamation"></i> <?= $erro ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Utilizador</label>
                <div class="input-container">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" class="form-input" placeholder="Ex: admin" required>
                </div>
            </div>

            <div class="form-group">
                <label>Palavra-passe</label>
                <div class="input-container">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="pwd" class="form-input" placeholder="••••••••" required>
                </div>
            </div>
            <!-- Criação de conta externa desativada -->

            <button type="submit" class="btn-login">
                Aceder
            </button>
        </form>

        <div style="margin-top: 30px; color: #64748b; font-size: 0.75rem;">
            &copy; <?= date("Y") ?> TSTORE • v1.1
        </div>
    </div>

</body>

</html>