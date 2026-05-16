<?php
// Garantir que as notificações estão carregadas
include_once __DIR__ . '/../actions/notifications_actions.php';
checkStockAlerts();
$notif_count = getUnreadCount();
$role = $_SESSION["role"] ?? 1;
$is_admin = ($role == 0);
?>
<nav class="navbar">
    <div class="nav-logo">
        <i class="fas fa-ghost"></i><span class="t-letter">T</span><span class="store-text">STORE</span>
    </div>

    <button class="hamburger" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="nav-links" id="navLinks">
        <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" onclick="location.href='index.php'">
            <i class="fas fa-chart-line"></i> Dashboard
        </button>
        <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'stock.php') ? 'active' : ''; ?>" onclick="location.href='stock.php'">
            <i class="fas fa-boxes-stacked"></i> Stock
        </button>
        <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'movimentos.php') ? 'active' : ''; ?>" onclick="location.href='movimentos.php'">
            <i class="fas fa-exchange-alt"></i> Movimentos
        </button>
        
        <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'encomendas.php') ? 'active' : ''; ?>" onclick="location.href='encomendas.php'">
            <i class="fas fa-shopping-cart"></i> Encomendas
        </button>

        <?php if ($is_admin): ?>
            <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'gestao.php') ? 'active' : ''; ?>" onclick="location.href='gestao.php'">
                <i class="fas fa-sliders"></i> Gestão
            </button>
        <?php endif; ?>
    </div>

    <div class="nav-right">
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
                        while ($n = $notifs->fetch_assoc()) {
                            ?>
                            <div class="notif-item">
                                <div class="notif-content">
                                    <div class="notif-msg"><?php echo htmlspecialchars($n['mensagem']); ?></div>
                                    <div class="notif-date">
                                        <?php echo date('d/m H:i', strtotime($n['data_criacao'])); ?></div>
                                </div>
                                <a href="javascript:void(0)"
                                    onclick="dismissNotification(<?php echo $n['id_notificacao']; ?>, this)"
                                    class="notif-action" title="Marcar como lida"><i class="fas fa-check"></i></a>
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
        <span class="user-name clickable" onclick="location.href='perfil.php'" title="Ver Perfil">
            <i class="fas fa-user-circle"></i>
            <?php echo htmlspecialchars($_SESSION["user"]); ?>
            <span class="role-badge" style="background: <?php 
                $role = $_SESSION['role'] ?? 1;
                if ($role == 0) echo '#bc6ff1';
                elseif ($role == 2) echo '#10b981';
                else echo '#4b5563';
            ?>;">
                <?php 
                $role = $_SESSION['role'] ?? 1;
                if ($role == 0) echo 'ADMIN';
                elseif ($role == 2) echo 'VENDEDOR';
                else echo 'USER';
                ?>
            </span>
        </span>
        <button class="nav-btn logout-btn" onclick="location.href='logout.php'">
            <i class="fas fa-power-off"></i> Sair
        </button>
    </div>
</nav>

<script>
function toggleMobileMenu() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('active');
}

// Fechar menu ao clicar fora
document.addEventListener('click', function(e) {
    const navLinks = document.getElementById('navLinks');
    const hamburger = document.querySelector('.hamburger');
    
    if (!navLinks.contains(e.target) && !hamburger.contains(e.target)) {
        navLinks.classList.remove('active');
    }
});
</script>
