<?php
/**
 * @file configuracoes.php
 * @brief Gestão de Definições de Marca e Sistema - TSTORE.
 */

include 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_config'])) {
    foreach ($_POST['config'] as $key => $value) {
        $stmt = $conn->prepare("UPDATE config SET param_value = ? WHERE param_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
    }
    $mensagem = "Configurações guardadas com sucesso!";
    // Recarregar configurações após update
    $res_config = $conn->query("SELECT param_key, param_value FROM config");
    while ($row = $res_config->fetch_assoc()) {
        $settings[$row['param_key']] = $row['param_value'];
    }
}

// Buscar configs detalhadas para o form
$res_full = $conn->query("SELECT * FROM config ORDER BY tab_group, label");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Configurações - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .config-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .config-group {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(188, 111, 241, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .config-group h3 {
            color: #bc6ff1;
            margin-bottom: 20px;
            font-size: 1.1rem;
            border-bottom: 1px solid rgba(188, 111, 241, 0.2);
            padding-bottom: 10px;
        }
        .config-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 20px;
        }
        .config-item label {
            flex: 1;
            text-transform: none;
            font-size: 0.9rem;
            font-weight: 400;
            color: #ccc;
        }
        .config-item input {
            width: 300px;
        }
        .success-bar {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #10b981;
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
                <button class="nav-btn" onclick="location.href='index.php'"><i class="fas fa-chart-line"></i> Dashboard</button>
                <button class="nav-btn" onclick="location.href='stock.php'"><i class="fas fa-boxes-stacked"></i> Stock</button>
                <button class="nav-btn" onclick="location.href='movimentos.php'"><i class="fas fa-exchange-alt"></i> Movimentos</button>
                <button class="nav-btn active" onclick="location.href='gestao.php'"><i class="fas fa-sliders"></i> Gestão</button>
            </div>
            <div class="nav-right">
                <span class="user-name clickable" onclick="location.href='perfil.php'">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION["user"]); ?>
                </span>
                <button class="nav-btn logout-btn" onclick="location.href='logout.php'"><i class="fas fa-power-off"></i> Sair</button>
            </div>
        </nav>

        <main class="content-area">
            <header class="table-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-btn" onclick="location.href='index.php'"><i class="fas fa-arrow-left" style="margin:0;"></i></button>
                    <div>
                        <h2>Configurações do Sistema</h2>
                        <p>Personalize a identidade e parâmetros globais</p>
                    </div>
                </div>
            </header>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="config-group">
                    <h3><i class="fas fa-percentage"></i> Gestão de IVA</h3>
                    <p style="color: #ccc; margin-bottom: 15px;">Configure taxas de IVA e associação a categorias</p>
                    <button class="nav-btn" onclick="location.href='configuracoes_iva.php'" style="width: 100%;">
                        <i class="fas fa-cog"></i> Configurar IVA
                    </button>
                </div>

                <div class="config-group">
                    <h3><i class="fas fa-envelope"></i> Notificações por Email</h3>
                    <p style="color: #ccc; margin-bottom: 15px;">Configure SMTP e alertas automáticos</p>
                    <button class="nav-btn" onclick="location.href='configuracoes_email.php'" style="width: 100%;">
                        <i class="fas fa-cog"></i> Configurar Email
                    </button>
                </div>

                <div class="config-group">
                    <h3><i class="fas fa-database"></i> Migração de Dados</h3>
                    <p style="color: #ccc; margin-bottom: 15px;">Execute migrações para novas funcionalidades</p>
                    <button class="nav-btn" onclick="location.href='migrar_novas_funcionalidades.php'" style="width: 100%;">
                        <i class="fas fa-play"></i> Executar Migração
                    </button>
                </div>
            </div>

            <?php if ($mensagem): ?><div class="success-bar"><?php echo $mensagem; ?></div><?php endif; ?>

            <form method="POST" class="config-container">
                <div class="config-group">
                    <h3><i class="fas fa-palette"></i> Identidade & Marca</h3>
                    <?php 
                    $res_full->data_seek(0);
                    while($row = $res_full->fetch_assoc()): 
                        if ($row['tab_group'] == 'GERAL' && $row['param_key'] != 'app_name'):
                    ?>
                        <div class="config-item">
                            <label><?php echo $row['label']; ?></label>
                            <input type="text" name="config[<?php echo $row['param_key']; ?>]" value="<?php echo htmlspecialchars($row['param_value']); ?>">
                        </div>
                    <?php 
                        endif;
                    endwhile; 
                    ?>
                </div>

                <div class="btn-group">
                    <button type="submit" name="save_config" class="nav-btn active">
                        <i class="fas fa-save"></i> Guardar Alterações
                    </button>
                </div>
            </form>
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
