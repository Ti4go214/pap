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
        <nav class="navbar">
            <div class="nav-logo">
                <i class="fas fa-ghost"></i><span class="t-letter">T</span><span class="store-text">STORE</span>
            </div>

            <button class="hamburger" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>

            <div class="nav-links" id="navLinks">
                <button class="nav-btn" onclick="location.href='index.php'">
                    <i class="fas fa-chart-line"></i> Dashboard
                </button>
                <button class="nav-btn" onclick="location.href='stock.php'">
                    <i class="fas fa-boxes-stacked"></i> Stock
                </button>
                <button class="nav-btn" onclick="location.href='movimentos.php'">
                    <i class="fas fa-exchange-alt"></i> Movimentos
                </button>

                <?php if ($is_admin): ?>
                    <button class="nav-btn" onclick="location.href='gestao.php'">
                        <i class="fas fa-sliders"></i> Gestão
                    </button>
                <?php endif; ?>
            </div>

            <div class="nav-right">
                                <?php 
                include_once 'actions/notifications_actions.php';
                checkStockAlerts();
                $notif_count = getUnreadCount();
                ?>
                <div class="notification-bell-container">
                    <div class="notification-bell" onclick="toggleNotifications()" title="Notificações">
                        <i class="fas fa-bell"></i>
                        <?php if ($notif_count > 0): ?><span class="bell-badge"><?php echo $notif_count; ?></span><?php endif; ?>
                    </div>
                    
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span><i class="fas fa-bell"></i> Alertas</span>
                        </div>
                        <div class="notif-body">
                            <?php
                            $notifs = getUnreadNotifications();
                            if ($notifs && $notifs->num_rows > 0) {
                                while($n = $notifs->fetch_assoc()) {
                                    ?>
                                    <div class="notif-item">
                                        <div class="notif-content">
                                            <div class="notif-msg"><?php echo htmlspecialchars($n['mensagem']); ?></div>
                                            <div class="notif-date"><?php echo date('d/m H:i', strtotime($n['data_criacao'])); ?></div>
                                        </div>
                                        <a href="javascript:void(0)" onclick="dismissNotification(<?php echo $n['id_notificacao']; ?>, this)" class="notif-action" title="Marcar como lida"><i class="fas fa-check"></i></a>
                                    </div>
                                    <?php
                                }
                            } else {
                                echo "<div class='no-notifs'>Sem alertas pendentes.</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <span class="user-name clickable active" onclick="location.href='perfil.php'" title="Ver Perfil">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['user']); ?>
                    <span class="role-badge" style="background: <?php echo $is_admin ? '#bc6ff1' : '#4b5563'; ?>;">
                        <?php echo $is_admin ? 'ADMIN' : 'USER'; ?>
                    </span>
                </span>
                <button class="nav-btn logout-btn" onclick="location.href='logout.php'">
                    <i class="fas fa-power-off"></i> Sair
                </button>
            </div>
        </nav>

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

function dismissNotification(id, element) {
    if (confirm('Marcar como lida?')) {
        fetch('actions/notifications_actions.php?mark_read=' + id + '&ajax=1')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = element.closest('.notif-item');
                    const body = item.parentElement;
                    item.remove();
                    
                    // Atualizar badge
                    const badge = document.querySelector('.bell-badge');
                    if (badge) {
                        let count = parseInt(badge.innerText) - 1;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.innerText = count;
                        }
                    }
                    
                    // Se não houver mais notificações, mostrar mensagem
                    if (body.querySelectorAll('.notif-item').length === 0) {
                        body.innerHTML = '<div class="no-notifs">Sem alertas pendentes.</div>';
                    }
                }
            });
    }
}

function toggleNotifications() {
    document.getElementById('notifDropdown').classList.toggle('show');
    document.querySelector('.notification-bell').classList.toggle('active');
}
window.addEventListener('click', function(e) {
    if (!e.target.closest('.notification-bell-container')) {
        const dropdown = document.getElementById('notifDropdown');
        const bell = document.querySelector('.notification-bell');
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            bell.classList.remove('active');
        }
    }
});

function toggleMobileMenu() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('active');
}

document.addEventListener('click', function(e) {
    const navLinks = document.getElementById('navLinks');
    const hamburger = document.querySelector('.hamburger');
    
    if (!navLinks.contains(e.target) && !hamburger.contains(e.target)) {
        navLinks.classList.remove('active');
    }
});
</script>
</body>
</html>
