<?php
include 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE username = ?");
    $stmt->bind_param("s", $_SESSION["user"]);
    $stmt->execute();
    $_SESSION['role'] = $stmt->get_result()->fetch_assoc()['role'] ?? 1;
}

if (!isset($_GET['id']) || !isset($_GET['tipo'])) {
    header("Location: movimentos.php");
    exit();
}

$id = intval($_GET['id']);
$tipo = $_GET['tipo'];

// Obter cabeçalho
if ($tipo == 'ENTRADA') {
    $stmt = $conn->prepare("SELECT n_cab as id_doc, cliente as entidade, data FROM ent_cab WHERE n_cab = ?");
} else {
    $stmt = $conn->prepare("SELECT n_cab as id_doc, cliente as entidade, data FROM sai_cab WHERE n_cab = ?");
}

if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $header = $res->fetch_assoc();
} else {
    $header = false;
}

if (!$header) {
    die("Movimento não encontrado ou erro na base de dados.");
}

// Obter linhas
$stmt_lines = $conn->prepare("
    SELECT l.*, p.descricao as prod_nome 
    FROM linhas l 
    LEFT JOIN produtos p ON l.id_produto = p.id_produto 
    WHERE l.id = ?
    ORDER BY l.n_linha ASC
");
$stmt_lines->bind_param("i", $id);
$stmt_lines->execute();
$lines_res = $stmt_lines->get_result();
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Detalhes do Movimento - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }

        .details-panel {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(188, 111, 241, 0.1);
            margin-bottom: 20px;
        }

        .header-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-box {
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 10px;
        }

        .info-box span {
            display: block;
            color: #aaa;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .info-box strong {
            color: #fff;
            font-size: 1.1rem;
        }
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
                <button class="nav-btn active" onclick="location.href='movimentos.php'">
                    <i class="fas fa-exchange-alt"></i> Movimentos
                </button>

                <?php if (($_SESSION['role'] ?? 1) == 0): ?>
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
                <div>
                    <h2>Detalhes do Movimento #
                        <?php echo $header['id_doc']; ?>
                    </h2>
                    <p>Visualização das linhas e informações gerais</p>
                </div>
            </header>

            <div class="details-panel">
                <div class="header-info">
                    <div class="info-box">
                        <span>Tipo</span>
                        <strong style="color: <?php echo $tipo == 'ENTRADA' ? '#10b981' : '#ff4b2b'; ?>;">
                            <i class="fas <?php echo $tipo == 'ENTRADA' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                            <?php echo htmlspecialchars($tipo); ?>
                        </strong>
                    </div>
                    <div class="info-box">
                        <span>Entidade</span>
                        <strong>
                            <?php echo htmlspecialchars($header['entidade']); ?>
                        </strong>
                    </div>
                    <div class="info-box">
                        <span>Data</span>
                        <strong>
                            <?php echo date("d/m/Y H:i", strtotime($header['data'])); ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Linha</th>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th>Preço Unitário</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($lines_res && $lines_res->num_rows > 0) {
                            $total_doc = 0;
                            while ($line = $lines_res->fetch_assoc()) {
                                $total_linha = $line['quantidade'] * $line['preço'];
                                $total_doc += $total_linha;
                                ?>
                                <tr>
                                    <td>
                                        <?php echo $line['n_linha']; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($line['prod_nome'] ? $line['prod_nome'] : "ID " . $line['id_produto']); ?>
                                    </td>
                                    <td>
                                        <?php echo $line['quantidade']; ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($line['preço'], 2, ',', '.'); ?> <?php echo $currency; ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($total_linha, 2, ',', '.'); ?> <?php echo $currency; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                            <tr style="background: rgba(188, 111, 241, 0.1);">
                                <td colspan="4" style="text-align: right; font-weight: bold;">TOTAL DO DOCUMENTO:</td>
                                <td style="font-weight: bold; color: #bc6ff1;">
                                    <?php echo number_format($total_doc, 2, ',', '.'); ?> <?php echo $currency; ?>
                                </td>
                            </tr>
                            <?php
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; padding: 20px;'>Nenhuma linha encontrada.</td></tr>";
                        }
                        ?>
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