<?php
/**
 * @file configuracoes_iva.php
 * @brief Página de configuração de taxas de IVA
 * @author Antigravity
 * @date 2026-05-12
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

// Processar formulário
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'adicionar' || $action === 'editar') {
        $nome = $_POST['nome'] ?? '';
        $taxa = $_POST['taxa'] ?? 0;
        $padrao = isset($_POST['padrao']) ? 1 : 0;
        $descricao = $_POST['descricao'] ?? '';
        
        if (!empty($nome) && $taxa >= 0) {
            if ($action === 'adicionar') {
                // Se for padrão, remover o padrão anterior
                if ($padrao) {
                    $conn->query("UPDATE iva_taxas SET padrao = 0");
                }
                
                $stmt = $conn->prepare("INSERT INTO iva_taxas (nome, taxa, padrao, descricao) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sdis", $nome, $taxa, $padrao, $descricao);
                $stmt->execute();
                
                registarLog($_SESSION['id_user'], 'IVA_ADICIONADO', "Taxa IVA: $nome - $taxa%");
                $success = "Taxa de IVA adicionada com sucesso!";
            } elseif ($action === 'editar') {
                $id = $_POST['id'] ?? 0;
                
                // Se for padrão, remover o padrão anterior
                if ($padrao) {
                    $conn->query("UPDATE iva_taxas SET padrao = 0 WHERE id_taxa != $id");
                }
                
                $stmt = $conn->prepare("UPDATE iva_taxas SET nome=?, taxa=?, padrao=?, descricao=? WHERE id_taxa=?");
                $stmt->bind_param("sdisi", $nome, $taxa, $padrao, $descricao, $id);
                $stmt->execute();
                
                registarLog($_SESSION['id_user'], 'IVA_EDITADO', "Taxa IVA ID: $id");
                $success = "Taxa de IVA atualizada com sucesso!";
            }
        } else {
            $error = "Preencha todos os campos obrigatórios.";
        }
    } elseif ($action === 'eliminar') {
        $id = $_POST['id'] ?? 0;
        
        // Verificar se não está a ser usada
        $check_sql = "SELECT COUNT(*) as count FROM categoria WHERE id_iva = $id";
        $check_result = $conn->query($check_sql);
        $count = $check_result->fetch_assoc()['count'];
        
        if ($count == 0) {
            $stmt = $conn->prepare("DELETE FROM iva_taxas WHERE id_taxa=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Taxa de IVA eliminada com sucesso!";
                registarLog($_SESSION['id_user'], 'IVA_ELIMINADO', "Taxa IVA ID: $id");
            } else {
                $error = "Erro ao eliminar taxa de IVA: " . $conn->error;
            }
        } else {
            $error = "Esta taxa de IVA está a ser usada por $count categorias e não pode ser eliminada.";
        }
    } elseif ($action === 'associar_categoria') {
        $id_categoria = trim($_POST['id_categoria'] ?? '');
        $id_iva = $_POST['id_iva'] ?? '';
        
        $id_iva_val = (!empty($id_iva) && $id_iva !== '0') ? (int)$id_iva : null;
        
        if ($id_iva_val === null) {
            $stmt = $conn->prepare("UPDATE categoria SET id_iva=NULL WHERE id_categoria=?");
            $stmt->bind_param("s", $id_categoria);
        } else {
            $stmt = $conn->prepare("UPDATE categoria SET id_iva=? WHERE id_categoria=?");
            $stmt->bind_param("is", $id_iva_val, $id_categoria);
        }
        
        if ($stmt->execute()) {
            $success = "Categoria atualizada com taxa de IVA!";
            registarLog($_SESSION['id_user'], 'CATEGORIA_IVA_ATUALIZADO', "Categoria ID: $id_categoria - IVA ID: " . ($id_iva_val ?? 'NULL'));
        } else {
            $error = "Erro ao atualizar categoria: " . $conn->error;
        }
    }
}

// Buscar taxas de IVA
$ivas = $conn->query("SELECT * FROM iva_taxas ORDER BY padrao DESC, nome ASC");

// Buscar categorias com as suas taxas de IVA
$categorias = $conn->query("SELECT c.*, iv.nome as iva_nome, iv.taxa as iva_taxa 
                              FROM categoria c 
                              LEFT JOIN iva_taxas iv ON c.id_iva = iv.id_taxa 
                              ORDER BY c.descricao ASC");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Configurações de IVA - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .iva-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(188, 111, 241, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .iva-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .iva-nome {
            font-size: 1.3rem;
            font-weight: bold;
            color: #bc6ff1;
        }
        
        .iva-taxa {
            font-size: 1.5rem;
            font-weight: bold;
            color: #10b981;
        }
        
        .badge-padrao {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 10px;
        }
        
        .iva-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 3px;
        }
        
        .info-value {
            font-size: 1rem;
            color: #fff;
            font-weight: bold;
        }
        
        .categoria-iva {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(188, 111, 241, 0.1);
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .categoria-nome {
            font-weight: bold;
            color: #ccc;
        }
        
        .iva-atual {
            color: #10b981;
            font-weight: bold;
        }
        
        .sem-iva {
            color: #f59e0b;
            font-style: italic;
        }
        
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #6b7280;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #10b981;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>
        
        <main class="content-area">
            <header class="table-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-btn" onclick="location.href='configuracoes.php'" title="Voltar">
                        <i class="fas fa-arrow-left" style="margin:0;"></i>
                    </button>
                    <div>
                        <h2>Configurações de IVA</h2>
                        <p>Gerir taxas de IVA e associar a categorias</p>
                    </div>
                </div>
                <button class="nav-btn active" onclick="abrirModal()">
                    <i class="fas fa-plus"></i> Nova Taxa
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

            <!-- Taxas de IVA -->
            <div style="margin-bottom: 40px;">
                <h3 style="color: #bc6ff1; margin-bottom: 20px;">Taxas de IVA Configuradas</h3>
                
                <?php if ($ivas && $ivas->num_rows > 0): ?>
                    <?php while ($iva = $ivas->fetch_assoc()): ?>
                        <div class="iva-card">
                            <div class="iva-header">
                                <div>
                                    <div class="iva-nome"><?php echo htmlspecialchars($iva['nome']); ?></div>
                                    <div style="color: #888; font-size: 0.9rem;"><?php echo htmlspecialchars($iva['descricao']); ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="iva-taxa"><?php echo number_format($iva['taxa'], 2, ',', '.'); ?>%</div>
                                    <?php if ($iva['padrao']): ?>
                                        <span class="badge-padrao">Padrão</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px; display: flex; gap: 10px;">
                                <button class="nav-btn" onclick="editarIva(<?php echo $iva['id_taxa']; ?>)" style="padding: 8px 15px; font-size: 0.9rem;">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="nav-btn" onclick="eliminarIva(<?php echo $iva['id_taxa']; ?>)" style="padding: 8px 15px; font-size: 0.9rem; background: rgba(239,68,68,0.2);">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 50px; opacity: 0.5;">
                        <i class="fas fa-percentage" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>Sem taxas de IVA configuradas.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Categorias e suas taxas de IVA -->
            <div>
                <h3 style="color: #bc6ff1; margin-bottom: 20px;">Categorias e Taxas de IVA</h3>
                
                <?php if ($categorias && $categorias->num_rows > 0): ?>
                    <?php while ($categoria = $categorias->fetch_assoc()): ?>
                        <div class="categoria-iva">
                            <div>
                                <div class="categoria-nome"><?php echo htmlspecialchars($categoria['descricao']); ?></div>
                                <div style="color: #888; font-size: 0.8rem;">
                                    ID: <?php echo $categoria['id_categoria']; ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($categoria['id_iva']): ?>
                                    <span class="iva-atual">
                                        <?php echo htmlspecialchars($categoria['iva_nome']); ?> 
                                        (<?php echo number_format($categoria['iva_taxa'], 2, ',', '.'); ?>%)
                                    </span>
                                <?php else: ?>
                                    <span class="sem-iva">Sem IVA</span>
                                <?php endif; ?>
                                
                                <select onchange="atualizarIvaCategoria('<?php echo htmlspecialchars(addslashes($categoria['id_categoria'])); ?>', this.value)" style="padding: 6px; border-radius: 5px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(188,111,241,0.3);">
                                    <option value="">Alterar IVA</option>
                                    <?php 
                                    // Resetar ponteiro das taxas
                                    $ivas->data_seek(0);
                                    while ($iva = $ivas->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $iva['id_taxa']; ?>" <?php echo $categoria['id_iva'] == $iva['id_taxa'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($iva['nome']); ?> (<?php echo number_format($iva['taxa'], 2, ',', '.'); ?>%)
                                        </option>
                                    <?php endwhile; ?>
                                    <option value="0">Remover IVA</option>
                                </select>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 50px; opacity: 0.5;">
                        <i class="fas fa-tags" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>Sem categorias registadas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal IVA -->
    <div id="ivaModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="fecharModal()">&times;</span>
            <h2 id="modalTitle">Nova Taxa de IVA</h2>
            <form method="POST" id="ivaForm">
                <input type="hidden" name="action" id="formAction" value="adicionar">
                <input type="hidden" name="id" id="ivaId">
                
                <div class="modal-grid">
                    <div class="input-group">
                        <label>Nome *</label>
                        <input type="text" name="nome" id="nomeIva" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Taxa (%) *</label>
                        <input type="number" name="taxa" id="taxaIva" step="0.01" min="0" max="100" required>
                    </div>
                </div>
                
                <div class="input-group full-width">
                    <label>Descrição</label>
                    <textarea name="descricao" id="descricaoIva" rows="3"></textarea>
                </div>
                
                <div class="input-group full-width">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <label class="switch">
                            <input type="checkbox" name="padrao" id="padraoIva">
                            <span class="slider"></span>
                        </label>
                        <span>Taxa Padrão</span>
                    </label>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" onclick="fecharModal()" class="nav-btn" style="background: rgba(107, 114, 128, 0.2);">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="nav-btn active">
                        <i class="fas fa-save"></i> Guardar Taxa
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() {
            document.getElementById("modalTitle").innerText = "Nova Taxa de IVA";
            document.getElementById("formAction").value = "adicionar";
            document.getElementById("ivaId").value = "";
            document.getElementById("ivaForm").reset();
            document.getElementById("ivaModal").style.display = "flex";
        }
        
        function fecharModal() {
            document.getElementById("ivaModal").style.display = "none";
        }
        
        function editarIva(id) {
            showToast("Funcionalidade de edição em desenvolvimento.", "info");
        }
        
        function eliminarIva(id) {
            showConfirm("Tem certeza que deseja eliminar esta taxa de IVA?", () => {
                const form = document.createElement("form");
                form.method = "POST";
                form.innerHTML = `
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }, "Eliminar Taxa IVA", "fa-percent");
        }
        
        function atualizarIvaCategoria(idCategoria, idIva) {
            showConfirm("Deseja alterar a taxa de IVA desta categoria?", () => {
                const form = document.createElement("form");
                form.method = "POST";
                form.innerHTML = `
                    <input type="hidden" name="action" value="associar_categoria">
                    <input type="hidden" name="id_categoria" value="${idCategoria}">
                    <input type="hidden" name="id_iva" value="${idIva}">
                `;
                document.body.appendChild(form);
                form.submit();
            }, "Atualizar IVA", "fa-tags");
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById("ivaModal");
            if (event.target == modal) {
                fecharModal();
            }
        }
    </script>
</body>
</html>
