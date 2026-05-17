<?php
/**
 * @file perfil.php
 * @brief Página de perfil do utilizador e alteração de password.
 * @author Antigravity
 * @date 2026-03-13
 */

include 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$success = "";
$error = "";

// Lógica para alterar a password
if (isset($_POST['btn_update_pwd'])) {
    $current_pwd = $_POST['current_pwd'];
    $new_pwd = $_POST['new_pwd'];
    $confirm_pwd = $_POST['confirm_pwd'];

    if ($new_pwd !== $confirm_pwd) {
        $error = "As novas passwords não coincidem!";
    } else {
        // Verificar password atual
        $stmt = $conn->prepare("SELECT pwd FROM users WHERE username = ?");
        $stmt->bind_param("s", $_SESSION['user']);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_data = $res->fetch_assoc();

        if (password_verify($current_pwd, $user_data['pwd'])) {
            $hashed_pwd = password_hash($new_pwd, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET pwd = ? WHERE username = ?");
            $upd->bind_param("ss", $hashed_pwd, $_SESSION['user']);
            
            if ($upd->execute()) {
                $success = "Password alterada com sucesso!";
                registarLog($_SESSION['id_user'], "ALTERACAO_PASSWORD", "O utilizador alterou a sua própria password.");
            } else {
                $error = "Erro ao atualizar a password.";
            }
        } else {
            $error = "A password atual está incorreta.";
        }
    }
}

$is_admin = (($_SESSION['role'] ?? 1) == 0);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>O Meu Perfil - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }

        .profile-container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(188, 111, 241, 0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-avatar {
            font-size: 5rem;
            color: #bc6ff1;
            margin-bottom: 10px;
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-error { background: rgba(255, 75, 43, 0.1); color: #ff4b2b; border: 1px solid rgba(255, 75, 43, 0.2); }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>

        <main class="content-area">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar"><i class="fas fa-user-astronaut"></i></div>
                    <h2>@<?php echo htmlspecialchars($_SESSION['user']); ?></h2>
                    <span class="role-badge" style="margin-left: 0; display: inline-block; margin-top: 10px;">
                        <?php echo $is_admin ? 'ADMINISTRADOR' : 'UTILIZADOR'; ?>
                    </span>
                </div>

                <?php if ($success): ?> <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>
                <?php if ($error): ?> <div class="alert alert-error"><?php echo $error; ?></div> <?php endif; ?>

                <form action="" method="POST">
                    <h3 style="color: #bc6ff1; margin-bottom: 20px; font-size: 1.1rem; border-bottom: 1px solid rgba(188, 111, 241, 0.1); padding-bottom: 10px;">Alterar Password</h3>
                    
                    <div class="input-group full-width" style="margin-bottom: 15px;">
                        <label>Password Atual</label>
                        <input type="password" name="current_pwd" required placeholder="Digite a password atual">
                    </div>

                    <div class="input-group full-width" style="margin-bottom: 15px;">
                        <label>Nova Password</label>
                        <input type="password" name="new_pwd" required placeholder="Digite a nova password">
                    </div>

                    <div class="input-group full-width" style="margin-bottom: 25px;">
                        <label>Confirmar Nova Password</label>
                        <input type="password" name="confirm_pwd" required placeholder="Repita a nova password">
                    </div>

                    <button type="submit" name="btn_update_pwd" class="nav-btn active" style="width: 100%;">
                        <i class="fas fa-key"></i> Atualizar Password
                    </button>
                </form>
            </div>
        </main>
    </div>
<script>

</script>
</body>
</html>
