<?php
/**
 * @file categorias.php
 * @brief Gestão de Categorias - TSTORE.
 * @author Antigravity
 * @date 2026-03-17
 */

include 'includes/db_connect.php';
include 'includes/ListManager.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteção de acesso: apenas administradores
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

$is_admin = true;

// Usar ListManager
$list = new ListManager($conn, 8); // 8 categories per page

$filtro_sql = $list->getFilterSQL(['descricao', 'id_categoria']);
$list->calculatePagination('categoria', $filtro_sql);
$offset = $list->offset;

$sql = "SELECT * FROM categoria $filtro_sql ORDER BY descricao ASC LIMIT 8 OFFSET $offset";
$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Categorias - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
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
                    <span class="role-badge" style="background: #bc6ff1;">ADMIN</span>
                </span>
                <button class="nav-btn logout-btn" onclick="location.href='logout.php'">
                    <i class="fas fa-power-off"></i> Sair
                </button>
            </div>
        </nav>

        <main class="content-area">
            <header class="table-header">
                <div>
                    <button class="nav-btn" onclick="location.href='gestao.php'" style="margin-bottom: 15px; border-color: #888; color: #aaa;">
                        <i class="fas fa-arrow-left"></i> Voltar à Gestão
                    </button>
                    <h2 style="color: #bc6ff1; margin-bottom: 5px;">Gestão de Categorias</h2>
                    <p style="color: #aaa; font-size: 0.9rem;">Total de categorias: <strong><?php echo $list->totalRecords; ?></strong></p>
                </div>
                <div class="header-actions">
                    <button class="nav-btn active" onclick="abrirModalCategoria()">
                        <i class="fas fa-folder-plus"></i> Nova Categoria
                    </button>
                </div>
            </header>

            <?php $list->renderSearchBar("Pesquisar categoria..."); ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID da Categoria</th>
                            <th>Descrição</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($resultado && $resultado->num_rows > 0) {
                            while ($row = $resultado->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row["id_categoria"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row["descricao"]); ?></td>
                                    <td style="text-align: right;">
                                        <div class="action-btns" style="justify-content: flex-end;">
                                            <button onclick="editarModalCategoria('<?php echo htmlspecialchars(addslashes($row['id_categoria'])); ?>', '<?php echo htmlspecialchars(addslashes($row['descricao'])); ?>')" class="edit-btn" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="eliminarCategoria('<?php echo htmlspecialchars(addslashes($row['id_categoria'])); ?>')" class="delete-btn" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='3' style='text-align:center; padding: 50px; opacity: 0.5;'>Nenhuma categoria encontrada.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <?php $list->renderPagination(); ?>

        </main>
    </div>

    <!-- MODAL DE CATEGORIA -->
    <div id="modalCategoria" class="modal">
        <div class="modal-content" style="background: #1e0f32; padding: 30px; border-radius: 15px; width: 100%; max-width: 500px; position: relative;">
            <span onclick="fecharModalCategoria()" class="close-modal">&times;</span>
            <h2 id="modalTitleCategoria" style="color: #bc6ff1; margin-bottom: 25px; font-size: 1.5rem;">Nova Categoria</h2>

            <form action="actions/categoria_actions.php" method="POST">
                <input type="hidden" name="old_id_categoria" id="c_old_id">

                <div class="input-group" style="margin-bottom: 20px;">
                    <label>ID da Categoria (Código/Slug)</label>
                    <input type="text" name="id_categoria" id="c_id" required placeholder="Ex: hardware, cat_01" maxlength="20">
                </div>
                
                <div class="input-group" style="margin-bottom: 30px;">
                    <label>Designação</label>
                    <input type="text" name="descricao" id="c_descricao" required placeholder="Ex: Componentes Informáticos" maxlength="40">
                </div>

                <div class="btn-group" style="margin-top: 20px;">
                    <button type="button" onclick="fecharModalCategoria()" class="nav-btn" style="background: transparent; border-color: #ff4b2b; color: #ff4b2b;">Cancelar</button>
                    <button type="submit" name="btn_save_categoria" class="nav-btn active">
                        <i class="fas fa-save"></i> Gravar Categoria
                    </button>
                </div>
            </form>
        </div>
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

    // Logística do Modal
    const modal = document.getElementById("modalCategoria");
    
    function abrirModalCategoria() {
        document.getElementById("modalTitleCategoria").innerText = "Nova Categoria";
        document.getElementById("c_old_id").value = "";
        document.getElementById("c_id").value = "";
        document.getElementById("c_id").readOnly = false; // Permite editar ID na criação
        document.getElementById("c_descricao").value = "";
        modal.style.display = "flex";
    }

    function editarModalCategoria(id, descricao) {
        document.getElementById("modalTitleCategoria").innerText = "Editar Categoria";
        document.getElementById("c_old_id").value = id;
        document.getElementById("c_id").value = id;
        document.getElementById("c_id").readOnly = true; // Não permite alterar a chave primária na edição (pode quebrar chaves estrangeiras)
        document.getElementById("c_descricao").value = descricao;
        modal.style.display = "flex";
    }

    function fecharModalCategoria() {
        modal.style.display = "none";
    }

    function eliminarCategoria(id) {
        if (confirm("Tem a certeza que deseja eliminar a categoria '" + id + "'?\nAtenção: a base de dados impedirá a eliminação se existirem produtos associados a esta categoria.")) {
            window.location.href = "actions/categoria_actions.php?delete_id=" + encodeURIComponent(id);
        }
    }

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
