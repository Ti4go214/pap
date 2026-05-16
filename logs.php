<?php
/**
 * @file logs.php
 * @brief Página de visualização de logs de auditoria (apenas para admins).
 * @author Antigravity
 * @date 2026-03-13
 */

include 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

// Paginação simples para logs
$limit = 50;
$res_count = $conn->query("SELECT COUNT(*) as total FROM logs");
$total = $res_count->fetch_assoc()['total'];

$sql = "SELECT l.*, u.username 
        FROM logs l 
        LEFT JOIN users u ON l.id_user = u.id_user
        ORDER BY l.data_hora DESC 
        LIMIT $limit";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Auditoria do Sistema - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }

        .log-tag {
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: bold;
            background: rgba(188, 111, 241, 0.1);
            color: #bc6ff1;
        }
        .tag-danger { background: rgba(255, 75, 43, 0.1); color: #ff4b2b; }
        .tag-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .tag-warning { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
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

                <?php if (($_SESSION['role'] ?? 1) == 0): ?>
                    <button class="nav-btn active" onclick="location.href='gestao.php'">
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

        <main class="content-area">
            <header class="table-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-btn" onclick="location.href='gestao.php'" title="Voltar para Gestão" style="padding: 10px 15px;">
                        <i class="fas fa-arrow-left" style="margin: 0;"></i>
                    </button>
                    <div>
                        <h2>Logs de Auditoria</h2>
                        <p>Visualização das últimas <?php echo $limit; ?> ações críticas no sistema</p>
                    </div>
                </div>
            </header>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Utilizador</th>
                            <th>Ação</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $tagClass = "";
                                if (str_contains($row['acao'], 'ELIMINACAO')) $tagClass = "tag-danger";
                                if (str_contains($row['acao'], 'CRIACAO') || str_contains($row['acao'], 'REGISTO')) $tagClass = "tag-success";
                                if (str_contains($row['acao'], 'ALTERACAO')) $tagClass = "tag-warning";
                            ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo date("d/m/Y H:i:s", strtotime($row['data_hora'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['username'] ?? 'Sistema/Antigo (' . $row['id_user'] . ')'); ?></strong>
                                    </td>
                                    <td><span class="log-tag <?php echo $tagClass; ?>"><?php echo str_replace('_', ' ', $row['acao']); ?></span></td>
                                    <td style="font-size: 0.9rem; opacity: 0.8;"><?php echo htmlspecialchars($row['detalhes']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding: 50px;">Sem logs registados ainda.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
