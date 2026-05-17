<?php
/**
 * @file fornecedores.php
 * @brief Página de gestão de fornecedores
 * @author Antigravity
 * @date 2026-04-28
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';
include 'includes/ListManager.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION["role"] ?? 1;
$is_admin = ($role == 0);

// Processar formulário de adicionar/editar fornecedor
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'adicionar' || $action === 'editar') {
        $nome = $_POST['nome'] ?? '';
        $nif = $_POST['nif'] ?? '';
        $email = $_POST['email'] ?? '';
        $telefone = $_POST['telefone'] ?? '';
        $morada = $_POST['morada'] ?? '';
        $cpostal = $_POST['cpostal'] ?? '';
        $localidade = $_POST['localidade'] ?? '';
        $pais = $_POST['pais'] ?? 'Portugal';
        $contacto_principal = $_POST['contacto_principal'] ?? '';
        $email_contacto = $_POST['email_contacto'] ?? '';
        
        if (!empty($nome)) {
            if ($action === 'adicionar') {
                $stmt = $conn->prepare("INSERT INTO fornecedores (nome, nif, email, telefone, morada, cpostal, localidade, pais, contacto_principal, email_contacto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssss", $nome, $nif, $email, $telefone, $morada, $cpostal, $localidade, $pais, $contacto_principal, $email_contacto);
                if ($stmt->execute()) {
                    $success = "Fornecedor adicionado com sucesso!";
                    registarLog($_SESSION['id_user'], 'FORNECEDOR_ADICIONADO', "Fornecedor: $nome");
                } else {
                    $error = "Erro ao adicionar fornecedor: " . $conn->error;
                }
            } elseif ($action === 'editar') {
                $id = $_POST['id'] ?? 0;
                $stmt = $conn->prepare("UPDATE fornecedores SET nome=?, nif=?, email=?, telefone=?, morada=?, cpostal=?, localidade=?, pais=?, contacto_principal=?, email_contacto=? WHERE id_fornecedor=?");
                $stmt->bind_param("ssssssssssi", $nome, $nif, $email, $telefone, $morada, $cpostal, $localidade, $pais, $contacto_principal, $email_contacto, $id);
                if ($stmt->execute()) {
                    $success = "Fornecedor atualizado com sucesso!";
                    registarLog($_SESSION['id_user'], 'FORNECEDOR_EDITADO', "Fornecedor ID: $id");
                } else {
                    $error = "Erro ao atualizar fornecedor: " . $conn->error;
                }
            }
        } else {
            $error = "O nome do fornecedor é obrigatório.";
        }
    } elseif ($action === 'eliminar') {
        $id = $_POST['id'] ?? 0;
        if ($is_admin || $role == 2) {
            $stmt = $conn->prepare("DELETE FROM fornecedores WHERE id_fornecedor=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Fornecedor eliminado com sucesso!";
                registarLog($_SESSION['id_user'], 'FORNECEDOR_ELIMINADO', "Fornecedor ID: $id");
            } else {
                $error = "Erro ao eliminar fornecedor: " . $conn->error;
            }
        } else {
            $error = "Apenas administradores podem eliminar fornecedores.";
        }
    }
}

// Buscar fornecedores com paginação
$list = new ListManager($conn, 10); // 10 fornecedores por página
$filtro_sql = $list->getFilterSQL(['nome', 'nif', 'email'], '');
$list->calculatePagination('fornecedores', $filtro_sql);
$offset = $list->offset;

$sql = "SELECT * FROM fornecedores $filtro_sql ORDER BY nome ASC LIMIT 10 OFFSET $offset";
$fornecedores = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Fornecedores - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>
        
        <main class="content-area">
            <header class="table-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-btn" onclick="location.href='gestao.php'" title="Voltar"><i class="fas fa-arrow-left" style="margin:0;"></i></button>
                    <div>
                        <h2>Gestão de Fornecedores</h2>
                        <p>Gerir base de dados de fornecedores</p>
                    </div>
                </div>
                <button class="nav-btn active" onclick="abrirModal()">
                    <i class="fas fa-plus"></i> Novo Fornecedor
                </button>
            </header>

            <?php if ($success): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: rgba(255, 75, 75, 0.1); border: 1px solid rgba(255, 75, 75, 0.3); color: #ff4b2b; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php $list->renderSearchBar('Pesquisar fornecedores...'); ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>NIF</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Contacto Principal</th>
                            <th>Localidade</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($fornecedores && $fornecedores->num_rows > 0): ?>
                            <?php while ($fornecedor = $fornecedores->fetch_assoc()): ?>
                            <tr>
                                <td data-label="ID"><?php echo $fornecedor['id_fornecedor']; ?></td>
                                <td data-label="Nome"><strong><?php echo htmlspecialchars($fornecedor['nome']); ?></strong></td>
                                <td data-label="NIF"><?php echo htmlspecialchars($fornecedor['nif'] ?? '-'); ?></td>
                                <td data-label="Email"><?php echo htmlspecialchars($fornecedor['email'] ?? '-'); ?></td>
                                <td data-label="Telefone"><?php echo htmlspecialchars($fornecedor['telefone'] ?? '-'); ?></td>
                                <td data-label="Contacto"><?php echo htmlspecialchars($fornecedor['contacto_principal'] ?? '-'); ?></td>
                                <td data-label="Localidade"><?php echo htmlspecialchars($fornecedor['localidade'] ?? '-'); ?></td>
                                <td data-label="Ações">
                                    <div class="action-btns">
                                        <button class="edit-btn" onclick="editarFornecedor(<?php echo $fornecedor['id_fornecedor']; ?>, '<?php echo htmlspecialchars($fornecedor['nome'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['nif'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['email'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['telefone'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['morada'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['cpostal'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['localidade'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['pais'] ?? 'Portugal', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['contacto_principal'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fornecedor['email_contacto'] ?? '', ENT_QUOTES); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($is_admin || ($_SESSION['role'] ?? 1) == 2): ?>
                                        <button class="delete-btn" onclick="eliminarFornecedor(<?php echo $fornecedor['id_fornecedor']; ?>, '<?php echo htmlspecialchars($fornecedor['nome'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 50px; opacity: 0.5;">
                                    <i class="fas fa-truck" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                    <p>Sem fornecedores registados.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php $list->renderPagination(); ?>
        </main>
    </div>

    <!-- Modal Fornecedor -->
    <div id="fornecedorModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="fecharModal()">&times;</span>
            <h2 id="modalTitle">Novo Fornecedor</h2>
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" id="formAction" value="adicionar">
                <input type="hidden" name="id" id="fornecedorId">
                
                <div class="input-group full-width">
                    <label>Nome *</label>
                    <input type="text" name="nome" id="fornecedorNome" required>
                </div>
                
                <div class="input-group">
                    <label>NIF</label>
                    <input type="text" name="nif" id="fornecedorNif">
                </div>
                
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" id="fornecedorEmail">
                </div>
                
                <div class="input-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" id="fornecedorTelefone">
                </div>
                
                <div class="input-group full-width">
                    <label>Morada</label>
                    <input type="text" name="morada" id="fornecedorMorada">
                </div>
                
                <div class="input-group">
                    <label>Código Postal</label>
                    <input type="text" name="cpostal" id="fornecedorCpostal">
                </div>
                
                <div class="input-group">
                    <label>Localidade</label>
                    <input type="text" name="localidade" id="fornecedorLocalidade" readonly>
                </div>
                
                <div class="input-group">
                    <label>País</label>
                    <input type="text" name="pais" id="fornecedorPais" value="Portugal">
                </div>
                
                <div class="input-group">
                    <label>Contacto Principal</label>
                    <input type="text" name="contacto_principal" id="fornecedorContacto">
                </div>
                
                <div class="input-group">
                    <label>Email Contacto</label>
                    <input type="email" name="email_contacto" id="fornecedorEmailContacto">
                </div>
                
                <div class="btn-group">
                    <button type="button" class="nav-btn" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" class="nav-btn active">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() {
            document.getElementById('fornecedorModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Novo Fornecedor';
            document.getElementById('formAction').value = 'adicionar';
            document.getElementById('fornecedorId').value = '';
            document.getElementById('fornecedorNome').value = '';
            document.getElementById('fornecedorNif').value = '';
            document.getElementById('fornecedorEmail').value = '';
            document.getElementById('fornecedorTelefone').value = '';
            document.getElementById('fornecedorMorada').value = '';
            document.getElementById('fornecedorCpostal').value = '';
            document.getElementById('fornecedorLocalidade').value = '';
            document.getElementById('fornecedorPais').value = 'Portugal';
            document.getElementById('fornecedorContacto').value = '';
            document.getElementById('fornecedorEmailContacto').value = '';
        }

        function fecharModal() {
            document.getElementById('fornecedorModal').style.display = 'none';
        }

        function editarFornecedor(id, nome, nif, email, telefone, morada, cpostal, localidade, pais, contacto_principal, email_contacto) {
            document.getElementById('fornecedorModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Editar Fornecedor';
            document.getElementById('formAction').value = 'editar';
            document.getElementById('fornecedorId').value = id;
            document.getElementById('fornecedorNome').value = nome;
            document.getElementById('fornecedorNif').value = nif;
            document.getElementById('fornecedorEmail').value = email;
            document.getElementById('fornecedorTelefone').value = telefone;
            document.getElementById('fornecedorMorada').value = morada;
            document.getElementById('fornecedorCpostal').value = cpostal;
            document.getElementById('fornecedorLocalidade').value = localidade;
            document.getElementById('fornecedorPais').value = pais;
            document.getElementById('fornecedorContacto').value = contacto_principal;
            document.getElementById('fornecedorEmailContacto').value = email_contacto;
        }

        function eliminarFornecedor(id, nome) {
            showConfirm('Tem a certeza que deseja eliminar o fornecedor "' + nome + '"?', () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }, "Eliminar Fornecedor", "fa-truck-loading");
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('fornecedorModal');
            if (event.target == modal) {
                fecharModal();
            }
        }

        // Preencher localidade automaticamente ao digitar código postal
        document.getElementById('fornecedorCpostal').addEventListener('blur', function() {
            const cpostal = this.value.trim();
            if (cpostal.length >= 4) {
                fetch('actions/get_localidade.php?cpostal=' + encodeURIComponent(cpostal))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('fornecedorLocalidade').value = data.localidade;
                        }
                    })
                    .catch(error => console.error('Erro ao buscar localidade:', error));
            }
        });
    </script>

<?php
// Lógica para abrir modal de edição automaticamente via URL
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $res_edit = $conn->query("SELECT * FROM fornecedores WHERE id_fornecedor = $edit_id");
    if ($res_edit && $row_edit = $res_edit->fetch_assoc()) {
        $nome = addslashes($row_edit['nome']);
        $nif = addslashes($row_edit['nif'] ?? '');
        $email = addslashes($row_edit['email'] ?? '');
        $tel = addslashes($row_edit['telefone'] ?? '');
        $morada = addslashes($row_edit['morada'] ?? '');
        $cp = addslashes($row_edit['cpostal'] ?? '');
        $loc = addslashes($row_edit['localidade'] ?? '');
        $pais = addslashes($row_edit['pais'] ?? 'Portugal');
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    editarFornecedor('$edit_id', '$nome', '$nif', '$email', '$tel', '$morada', '$cp', '$loc', '$pais');
                }, 500);
            });
        </script>";
    }
}
?>
</body>
</html>
