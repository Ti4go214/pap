<?php
/**
 * @file gestao.php
 * @brief Hub de Gestão Administrativa - TSTORE.
 * @author Antigravity
 * @date 2026-03-16
 */

include 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteção de acesso: apenas administradores
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

$is_admin = true;
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Gestão Administrativa - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>

        <main class="content-area">
            <header class="main-header">
                <h2>Portal de Gestão</h2>
                <p>Configurações avançadas e auditoria do sistema <strong>TSTORE</strong></p>
            </header>

            <div class="hub-grid">
                <!-- Card Categorias -->
                <a href="categorias.php" class="hub-card">
                    <div class="card-glow"></div>
                    <i class="fas fa-tags"></i>
                    <h3>Categorias</h3>
                    <p>Gerir as categorias (famílias) de produtos de informática disponíveis no inventário.</p>
                    <div class="nav-btn" style="pointer-events: none; margin-top: 10px;">
                        Gerir Catálogo
                    </div>
                </a>

                <!-- Card Utilizadores -->
                <a href="utilizadores.php" class="hub-card">
                    <div class="card-glow"></div>
                    <i class="fas fa-user-astronaut"></i>
                    <h3>Utilizadores</h3>
                    <p>Gerir contas, permissões e perfis de utilizadores do sistema.</p>
                    <div class="nav-btn" style="pointer-events: none; margin-top: 10px;">
                        Aceder Painel
                    </div>
                </a>

                <!-- Card Auditoria -->
                <a href="logs.php" class="hub-card">
                    <div class="card-glow"></div>
                    <i class="fas fa-list-ul"></i>
                    <h3>Auditoria</h3>
                    <p>Visualizar logs detalhados de todas as ações críticas e alterações.</p>
                    <div class="nav-btn" style="pointer-events: none; margin-top: 10px;">
                        Ver Registos
                    </div>
                </a>

                <!-- Card Relatórios Avançados -->
                <a href="relatorios.php" class="hub-card">
                    <div class="card-glow"></div>
                    <i class="fas fa-chart-pie"></i>
                    <h3>Relatórios</h3>
                    <p>Análise detalhada de performance, stock e fluxo financeiro.</p>
                    <div class="nav-btn" style="pointer-events: none; margin-top: 10px;">
                        Ver Estatísticas
                    </div>
                </a>

                <!-- Card Clientes -->
                <a href="clientes.php" class="hub-card">
                    <div class="card-glow"></div>
                    <i class="fas fa-users"></i>
                    <h3>Clientes</h3>
                    <p>Gerir base de dados de clientes com histórico de compras.</p>
                    <div class="nav-btn" style="pointer-events: none; margin-top: 10px;">
                        Gerir Clientes
                    </div>
                </a>

                <!-- Card Fornecedores -->
                <a href="fornecedores.php" class="hub-card">
                    <div class="card-glow"></div>
                    <i class="fas fa-truck"></i>
                    <h3>Fornecedores</h3>
                    <p>Gerir base de dados de fornecedores e contactos.</p>
                    <div class="nav-btn" style="pointer-events: none; margin-top: 10px;">
                        Gerir Fornecedores
                    </div>
                </a>

                <!-- Card Configurações -->
                <a href="configuracoes.php" class="hub-card">
                    <div class="card-glow"></div>
                    <i class="fas fa-cog"></i>
                    <h3>Configurações</h3>
                    <p>Configurar parâmetros do sistema (email, IVA, preferências globais).</p>
                    <div class="nav-btn" style="pointer-events: none; margin-top: 10px;">
                        Abrir Configurações
                    </div>
                </a>
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
</script>
</body>
</html>
