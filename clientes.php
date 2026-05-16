<?php
/**
 * @file clientes.php
 * @brief Página de gestão de clientes
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

// Processar formulário de adicionar/editar cliente
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
        
        if (!empty($nome)) {
            if ($action === 'adicionar') {
                $stmt = $conn->prepare("INSERT INTO clientes (nome, nif, email, telefone, morada, cpostal, localidade, pais) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $nome, $nif, $email, $telefone, $morada, $cpostal, $localidade, $pais);
                if ($stmt->execute()) {
                    $success = "Cliente adicionado com sucesso!";
                    registarLog($_SESSION['id_user'], 'CLIENTE_ADICIONADO', "Cliente: $nome");
                } else {
                    $error = "Erro ao adicionar cliente: " . $conn->error;
                }
            } elseif ($action === 'editar') {
                $id = $_POST['id'] ?? 0;
                $stmt = $conn->prepare("UPDATE clientes SET nome=?, nif=?, email=?, telefone=?, morada=?, cpostal=?, localidade=?, pais=? WHERE id_cliente=?");
                $stmt->bind_param("ssssssssi", $nome, $nif, $email, $telefone, $morada, $cpostal, $localidade, $pais, $id);
                if ($stmt->execute()) {
                    $success = "Cliente atualizado com sucesso!";
                    registarLog($_SESSION['id_user'], 'CLIENTE_EDITADO', "Cliente ID: $id");
                } else {
                    $error = "Erro ao atualizar cliente: " . $conn->error;
                }
            }
        } else {
            $error = "O nome do cliente é obrigatório.";
        }
    } elseif ($action === 'eliminar') {
        $id = $_POST['id'] ?? 0;
        if ($is_admin || $role == 2) {
            $stmt = $conn->prepare("DELETE FROM clientes WHERE id_cliente=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Cliente eliminado com sucesso!";
                registarLog($_SESSION['id_user'], 'CLIENTE_ELIMINADO', "Cliente ID: $id");
            } else {
                $error = "Erro ao eliminar cliente: " . $conn->error;
            }
        } else {
            $error = "Apenas administradores podem eliminar clientes.";
        }
    }
}

// Buscar clientes com paginação
$list = new ListManager($conn, 10); // 10 clientes por página
$filtro_sql = $list->getFilterSQL(['nome', 'nif', 'email'], '');
$list->calculatePagination('clientes', $filtro_sql);
$offset = $list->offset;

$sql = "SELECT * FROM clientes $filtro_sql ORDER BY nome ASC LIMIT 10 OFFSET $offset";
$clientes = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Clientes - TSTORE</title>
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
                        <h2>Gestão de Clientes</h2>
                        <p>Gerir base de dados de clientes</p>
                    </div>
                </div>
                <button class="nav-btn active" onclick="abrirModal()">
                    <i class="fas fa-plus"></i> Novo Cliente
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

            <?php $list->renderSearchBar('Pesquisar clientes...'); ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>NIF</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Morada</th>
                            <th>Localidade</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($clientes && $clientes->num_rows > 0): ?>
                            <?php while ($cliente = $clientes->fetch_assoc()): ?>
                            <tr>
                                <td data-label="ID"><?php echo $cliente['id_cliente']; ?></td>
                                <td data-label="Nome"><strong><?php echo htmlspecialchars($cliente['nome']); ?></strong></td>
                                <td data-label="NIF"><?php echo htmlspecialchars($cliente['nif'] ?? '-'); ?></td>
                                <td data-label="Email"><?php echo htmlspecialchars($cliente['email'] ?? '-'); ?></td>
                                <td data-label="Telefone"><?php echo htmlspecialchars($cliente['telefone'] ?? '-'); ?></td>
                                <td data-label="Morada"><?php echo htmlspecialchars($cliente['morada'] ?? '-'); ?></td>
                                <td data-label="Localidade"><?php echo htmlspecialchars($cliente['localidade'] ?? '-'); ?></td>
                                <td data-label="Ações">
                                    <div class="action-btns">
                                        <button class="edit-btn" onclick="editarCliente(<?php echo $cliente['id_cliente']; ?>, '<?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['nif'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['email'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['telefone'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['morada'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cpostal'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['localidade'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['pais'] ?? 'Portugal', ENT_QUOTES); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($is_admin || ($_SESSION['role'] ?? 1) == 2): ?>
                                        <button class="delete-btn" onclick="eliminarCliente(<?php echo $cliente['id_cliente']; ?>, '<?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES); ?>')">
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
                                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                    <p>Sem clientes registados.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php $list->renderPagination(); ?>
        </main>
    </div>

    <!-- Modal Cliente -->
    <div id="clienteModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="fecharModal()">&times;</span>
            <h2 id="modalTitle">Novo Cliente</h2>
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" id="formAction" value="adicionar">
                <input type="hidden" name="id" id="clienteId">
                
                <div class="input-group full-width">
                    <label>Nome *</label>
                    <input type="text" name="nome" id="clienteNome" required>
                </div>
                
                <div class="input-group">
                    <label>NIF</label>
                    <input type="text" name="nif" id="clienteNif">
                </div>
                
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" id="clienteEmail">
                </div>
                
                <div class="input-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" id="clienteTelefone">
                </div>
                
                <div class="input-group full-width">
                    <label>Morada</label>
                    <input type="text" name="morada" id="clienteMorada">
                </div>
                
                <div class="input-group">
                    <label>Código Postal</label>
                    <input type="text" name="cpostal" id="clienteCpostal">
                </div>
                
                <div class="input-group">
                    <label>Localidade</label>
                    <input type="text" name="localidade" id="clienteLocalidade" readonly>
                </div>
                
                <div class="input-group">
                    <label>País</label>
                    <input type="text" name="pais" id="clientePais" value="Portugal">
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
            document.getElementById('clienteModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Novo Cliente';
            document.getElementById('formAction').value = 'adicionar';
            document.getElementById('clienteId').value = '';
            document.getElementById('clienteNome').value = '';
            document.getElementById('clienteNif').value = '';
            document.getElementById('clienteEmail').value = '';
            document.getElementById('clienteTelefone').value = '';
            document.getElementById('clienteMorada').value = '';
            document.getElementById('clienteCpostal').value = '';
            document.getElementById('clienteLocalidade').value = '';
            document.getElementById('clientePais').value = 'Portugal';
        }

        function fecharModal() {
            document.getElementById('clienteModal').style.display = 'none';
        }

        function editarCliente(id, nome, nif, email, telefone, morada, cpostal, localidade, pais) {
            document.getElementById('clienteModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Editar Cliente';
            document.getElementById('formAction').value = 'editar';
            document.getElementById('clienteId').value = id;
            document.getElementById('clienteNome').value = nome;
            document.getElementById('clienteNif').value = nif;
            document.getElementById('clienteEmail').value = email;
            document.getElementById('clienteTelefone').value = telefone;
            document.getElementById('clienteMorada').value = morada;
            document.getElementById('clienteCpostal').value = cpostal;
            document.getElementById('clienteLocalidade').value = localidade;
            document.getElementById('clientePais').value = pais;
        }

        function eliminarCliente(id, nome) {
            if (confirm('Tem a certeza que deseja eliminar o cliente "' + nome + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('clienteModal');
            if (event.target == modal) {
                fecharModal();
            }
        }

        // Preencher localidade automaticamente ao digitar código postal
        document.getElementById('clienteCpostal').addEventListener('blur', function() {
            const cpostal = this.value.trim();
            if (cpostal.length >= 4) {
                fetch('actions/get_localidade.php?cpostal=' + encodeURIComponent(cpostal))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('clienteLocalidade').value = data.localidade;
                        }
                    })
                    .catch(error => console.error('Erro ao buscar localidade:', error));
            }
        });
    </script>
</body>
</html>
