<?php
include 'includes/db_connect.php';
include 'includes/ListManager.php';
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

if ($_SESSION['role'] != 0) {
    header("Location: index.php");
    exit();
}

$list = new ListManager($conn, 4);


// procura por username ou id_user
$filtro_sql = $list->getFilterSQL(['username', 'id_user']);
$list->calculatePagination('users', $filtro_sql);
$offset = $list->offset;

$utilizador_id = $_SESSION["utilizador_id"] ?? '';
$utilizador_nome = $_SESSION["utilizador_nome"] ?? '';

$sql = "SELECT * 
        FROM users
        $filtro_sql
        ORDER BY username
        LIMIT 4 OFFSET $offset";

$resultado = $conn->query($sql);
$result = $resultado;
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Gestão de Utilizadores - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }
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
                        <h2>Lista de Utilizadores</h2>
                        <p>Total de registos: <strong><?php echo $list->totalRecords; ?></strong></p>
                    </div>
                </div>
                <button class="nav-btn active" onclick="abrirModal()">
                    <i class="fas fa-plus"></i> Novo Registo
                </button>

            </header>

            <!-- Barra de Pesquisa via Classe -->
            <?php $list->renderSearchBar("Localizar Utilizador"); ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Nome</th>
                            <th>Cargo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td data-label="User"><strong><?php echo htmlspecialchars($row["id_user"]); ?></strong></td>
                                    <td data-label="Username"><?php echo htmlspecialchars($row["username"]); ?></td>
                                    <td data-label="Nome"><?php echo htmlspecialchars($row["nome"] ?? '-'); ?></td>
                                    <td data-label="Cargo"><?php if (strval($row["role"]) == 0) {
                                        echo htmlspecialchars("Admin");
                                    } elseif (strval($row["role"]) == 2) {
                                        echo htmlspecialchars("Vendedor");
                                    } else {
                                        echo htmlspecialchars("Utilizador");
                                    } ?>
                                    </td>
                                    <td data-label="Ações">
                                        <div class="action-btns">
                                            <button
                                                onclick="editarUtilizador('<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>', '<?php echo $row['role']; ?>', '<?php echo htmlspecialchars($row['nome'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['morada'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['postal'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['nascimento'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['nif'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['contacto'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['pais'] ?? 'Portugal', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['localidade'] ?? '', ENT_QUOTES); ?>')"
                                                class="edit-btn" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="eliminar('<?php echo $row['username']; ?>')" class="delete-btn"
                                                title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; padding: 50px; opacity: 0.5;'>Nenhum utilizador encontrado.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação via Classe -->
            <?php $list->renderPagination(); ?>

        </main>
    </div>

    <div id="modalUser" class="modal">
        <div class="modal-content" style="background: #1e0f32; padding: 20px; border-radius: 10px;">
            <span onclick="fecharModalUtilizador()" style="cursor:pointer; float:right; color:white;">&times;</span>
            <h2 id="modalTitle">Novo Utilizador</h2>
            <form action="actions/processar.php" method="POST">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="user" id="f_user" required>
                    </div>
                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="pwd" id="f_pwd">
                    </div>
                    <div class="input-group full-width">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" id="f_nome" required>
                    </div>
                    <div class="input-group full-width">
                        <label>Morada</label>
                        <input type="text" name="morada" id="f_morada">
                    </div>
                    <div class="input-group">
                        <label>Código Postal</label>
                        <input type="text" name="postal" id="f_postal">
                    </div>
                    <div class="input-group">
                        <label>Localidade</label>
                        <input type="text" name="localidade" id="f_localidade" readonly>
                    </div>
                    <div class="input-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="nascimento" id="f_nascimento">
                    </div>
                    <div class="input-group">
                        <label>NIF</label>
                        <input type="text" name="nif" id="f_nif">
                    </div>
                    <div class="input-group">
                        <label>Contacto</label>
                        <input type="text" name="contacto" id="f_contacto">
                    </div>
                    <div class="input-group full-width">
                        <label>País</label>
                        <input type="text" name="pais" id="f_pais" placeholder="Ex: Pt">
                    </div>
                    <div class="input-group full-width">
                        <label>Cargo</label>
                        <select name="role" id="f_role">
                            <option value="1">Utilizador</option>
                            <option value="2">Vendedor</option>
                            <option value="0">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" id="btn_submit" name="btn_save" class="nav-btn active">
                        <i class="fas fa-save"></i> Gravar
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>

function abrirModalUtilizador() {
    document.getElementById('modalUser').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Novo Utilizador';
    document.getElementById('f_user').value = '';
    document.getElementById('f_user').readOnly = false;
    document.getElementById('f_pwd').value = '';
    document.getElementById('f_pwd').placeholder = '';
    document.getElementById('f_nome').value = '';
    document.getElementById('f_morada').value = '';
    document.getElementById('f_postal').value = '';
    document.getElementById('f_nascimento').value = '';
    document.getElementById('f_nif').value = '';
    document.getElementById('f_contacto').value = '';
    document.getElementById('f_pais').value = 'Portugal';
    document.getElementById('f_localidade').value = '';
    document.getElementById('f_role').value = '1';
}

function fecharModalUtilizador() {
    document.getElementById('modalUser').style.display = 'none';
}

function editarUtilizador(username, role, nome, morada, postal, nascimento, nif, contacto, pais, localidade) {
    document.getElementById('modalUser').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Editar Utilizador';
    document.getElementById('f_user').value = username;
    document.getElementById('f_user').readOnly = true;
    document.getElementById('f_pwd').placeholder = '(Deixe vazio para manter)';
    document.getElementById('f_nome').value = nome;
    document.getElementById('f_morada').value = morada;
    document.getElementById('f_postal').value = postal;
    document.getElementById('f_nascimento').value = nascimento;
    document.getElementById('f_nif').value = nif;
    document.getElementById('f_contacto').value = contacto;
    document.getElementById('f_pais').value = pais;
    document.getElementById('f_localidade').value = localidade;
    document.getElementById('f_role').value = role;
}

function fecharModal() {
    fecharModalUtilizador();
}

function abrirModal() {
    abrirModalUtilizador();
}

function eliminar(username) {
    if(confirm("Tem a certeza que deseja eliminar o utilizador " + username + "?")) {
        window.location.href = "actions/processar.php?delete=" + username;
    }
}

// Preencher localidade automaticamente ao digitar código postal
document.addEventListener('DOMContentLoaded', function() {
    const postalField = document.getElementById('f_postal');
    if (postalField) {
        postalField.addEventListener('blur', function() {
            const cpostal = this.value.trim();
            if (cpostal.length >= 4) {
                fetch('actions/get_localidade.php?cpostal=' + encodeURIComponent(cpostal))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('f_localidade').value = data.localidade;
                        }
                    })
                    .catch(error => console.error('Erro ao buscar localidade:', error));
            }
        });
    }
});

function dismissNotification(id, element) {
    if (confirm('Marcar como lida?')) {
        fetch('actions/notifications_actions.php?mark_read=' + id + '&ajax=1')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = element.closest('.notif-item');
                    const body = item.parentElement;
                    item.remove();
                    
                    const badge = document.querySelector('.bell-badge');
                    if (badge) {
                        let count = parseInt(badge.innerText) - 1;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.innerText = count;
                        }
                    }
                    
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

<script>
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