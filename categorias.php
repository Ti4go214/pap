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
        <?php include 'includes/navbar.php'; ?>

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
        showConfirm("Tem a certeza que deseja eliminar a categoria '" + id + "'?\nAtenção: a base de dados impedirá a eliminação se existirem produtos associados a esta categoria.", () => {
            window.location.href = "actions/categoria_actions.php?delete_id=" + encodeURIComponent(id);
        }, "Eliminar Categoria", "fa-folder-minus");
    }
    </script>

<?php
// Lógica para abrir modal de edição automaticamente via URL
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $res_edit = $conn->query("SELECT * FROM categoria WHERE id_categoria = '$edit_id'");
    if ($res_edit && $row_edit = $res_edit->fetch_assoc()) {
        $id = addslashes($row_edit['id_categoria']);
        $desc = addslashes($row_edit['descricao']);
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    editarModalCategoria('$id', '$desc');
                }, 500);
            });
        </script>";
    }
}
?>
</body>
</html>
