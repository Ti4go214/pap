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
        <?php include 'includes/navbar.php'; ?>
        
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
    showConfirm("Tem a certeza que deseja eliminar o utilizador " + username + "?", () => {
        window.location.href = "actions/processar.php?delete=" + username;
    }, "Eliminar Utilizador", "fa-user-slash");
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

<?php
// Lógica para abrir modal de edição automaticamente via URL
if (isset($_GET['edit_id'])) {
    $edit_user = $_GET['edit_id'];
    $res_edit = $conn->query("SELECT * FROM users WHERE id_user = '$edit_user' OR username = '$edit_user'");
    if ($res_edit && $row_edit = $res_edit->fetch_assoc()) {
        $u = addslashes($row_edit['username']);
        $r = $row_edit['role'];
        $n = addslashes($row_edit['nome'] ?? '');
        $m = addslashes($row_edit['morada'] ?? '');
        $p = addslashes($row_edit['postal'] ?? '');
        $d = addslashes($row_edit['nascimento'] ?? '');
        $ni = addslashes($row_edit['nif'] ?? '');
        $c = addslashes($row_edit['contacto'] ?? '');
        $pa = addslashes($row_edit['pais'] ?? 'Portugal');
        $l = addslashes($row_edit['localidade'] ?? '');
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    editarUtilizador('$u', '$r', '$n', '$m', '$p', '$d', '$ni', '$c', '$pa', '$l');
                }, 500);
            });
        </script>";
    }
}
?>
</script>
</body>

</html>