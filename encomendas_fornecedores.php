<?php
/**
 * @file encomendas_fornecedores.php
 * @brief Página de gestão de encomendas a fornecedores (compras)
 * @author Antigravity
 * @date 2026-05-17
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';
include 'includes/ListManager.php';
include 'actions/email_notifications.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION["role"] ?? 1;
$is_admin = ($role == 0);
$pode_gerir = ($role == 0 || $role == 2);

/**
 * Garante que as tabelas encomendas_fornecedores e encomendas_fornecedores_linhas existem.
 * (Cria-as automaticamente se faltarem, para evitar erros fatais em instalações onde a migração não foi executada.)
 */
function ensureEncomendasFornecedoresSchema($conn) {
    $sql_encomendas_fornecedores = "CREATE TABLE IF NOT EXISTS encomendas_fornecedores (
        id_enc_fornecedor INT AUTO_INCREMENT PRIMARY KEY,
        num_encomenda VARCHAR(20) NOT NULL UNIQUE,
        id_fornecedor INT NOT NULL,
        data_encomenda DATE NOT NULL,
        estado ENUM('pendente','enviado','recebido','cancelado') DEFAULT 'pendente',
        observacoes TEXT,
        total_bruto DECIMAL(10,2) DEFAULT 0,
        total_liquido DECIMAL(10,2) DEFAULT 0,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_fornecedor) REFERENCES fornecedores(id_fornecedor)
    )";
    $conn->query($sql_encomendas_fornecedores);

    $sql_encomendas_forn_linhas = "CREATE TABLE IF NOT EXISTS encomendas_fornecedores_linhas (
        id_linha INT AUTO_INCREMENT PRIMARY KEY,
        id_enc_fornecedor INT NOT NULL,
        id_produto INT NOT NULL,
        quantidade INT NOT NULL,
        preco_unitario DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (id_enc_fornecedor) REFERENCES encomendas_fornecedores(id_enc_fornecedor) ON DELETE CASCADE,
        FOREIGN KEY (id_produto) REFERENCES produtos(id_produto)
    )";
    $conn->query($sql_encomendas_forn_linhas);
}

ensureEncomendasFornecedoresSchema($conn);

// Processar formulário
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if (!$pode_gerir) {
        $error = "Acesso negado: Apenas administradores e vendedores podem criar ou gerir encomendas a fornecedores.";
    } else {
        if ($action === 'adicionar' || $action === 'editar') {
        $id_fornecedor = (int)($_POST['id_fornecedor'] ?? 0);
        $data_encomenda = $_POST['data_encomenda'] ?? date('Y-m-d');
        $estado = $_POST['estado'] ?? 'pendente';
        $observacoes = $_POST['observacoes'] ?? '';
        $produtos = $_POST['produtos'] ?? [];
        
        if ($id_fornecedor > 0 && !empty($produtos)) {
            $conn->begin_transaction();
            
            try {
                // Calcular totais (sem IVA)
                $total_bruto = 0;
                
                foreach ($produtos as $p) {
                    $base = (float)$p['preco_unitario'] * (int)$p['quantidade'];
                    $total_bruto += $base;
                }
                
                $total_liquido = $total_bruto; // Sem descontos ou IVA
                
                if ($action === 'adicionar') {
                    $num_encomenda = 'EF' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $conn->prepare("INSERT INTO encomendas_fornecedores (num_encomenda, id_fornecedor, data_encomenda, estado, observacoes, total_bruto, total_liquido) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sisssdd", $num_encomenda, $id_fornecedor, $data_encomenda, $estado, $observacoes, $total_bruto, $total_liquido);
                    $stmt->execute();
                    $id_enc_fornecedor = $conn->insert_id;
                } else {
                    $id_enc_fornecedor = (int)$_POST['id_enc_fornecedor'];
                    
                    $stmt = $conn->prepare("UPDATE encomendas_fornecedores SET id_fornecedor=?, data_encomenda=?, estado=?, observacoes=?, total_bruto=?, total_liquido=? WHERE id_enc_fornecedor=?");
                    $stmt->bind_param("isssddi", $id_fornecedor, $data_encomenda, $estado, $observacoes, $total_bruto, $total_liquido, $id_enc_fornecedor);
                    $stmt->execute();
                    
                    $conn->query("DELETE FROM encomendas_fornecedores_linhas WHERE id_enc_fornecedor = $id_enc_fornecedor");
                }
                
                // Inserir linhas
                foreach ($produtos as $p) {
                    $id_p = (int)$p['id_produto'];
                    $qtd = (int)$p['quantidade'];
                    $prc = (float)$p['preco_unitario'];
                    
                    $stmt = $conn->prepare("INSERT INTO encomendas_fornecedores_linhas (id_enc_fornecedor, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiid", $id_enc_fornecedor, $id_p, $qtd, $prc);
                    $stmt->execute();
                }
                
                $conn->commit();
                
                if ($action === 'adicionar') {
                    registarLog($_SESSION['id_user'], 'ENC_FORNECEDOR_CRIADA', "Encomenda Fornecedor: $num_encomenda");
                } else {
                    registarLog($_SESSION['id_user'], 'ENC_FORNECEDOR_EDITADA', "Encomenda Fornecedor ID: $id_enc_fornecedor");
                }

                $success = "Encomenda a fornecedor guardada com sucesso!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Erro: " . $e->getMessage();
            }
        } else {
            $error = "Preencha todos os campos obrigatórios e adicione pelo menos um produto.";
        }
    } elseif ($action === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($is_admin) {
            $stmt = $conn->prepare("DELETE FROM encomendas_fornecedores WHERE id_enc_fornecedor=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Encomenda a fornecedor eliminada com sucesso!";
                registarLog($_SESSION['id_user'], 'ENC_FORNECEDOR_ELIMINADA', "Encomenda Fornecedor ID: $id");
            } else {
                $error = "Erro ao eliminar encomenda: " . $conn->error;
            }
        } else {
            $error = "Apenas administradores podem eliminar encomendas.";
        }
    } elseif ($action === 'atualizar_estado') {
        $id_enc_fornecedor = (int)($_POST['id_enc_fornecedor'] ?? 0);
        $novo_estado = $_POST['novo_estado'] ?? '';
        
        if (!empty($novo_estado)) {
            $res_enc = $conn->query("SELECT ef.estado, ef.id_fornecedor, f.nome as nome_fornecedor 
                                     FROM encomendas_fornecedores ef 
                                     LEFT JOIN fornecedores f ON ef.id_fornecedor = f.id_fornecedor 
                                     WHERE ef.id_enc_fornecedor = $id_enc_fornecedor");
            $enc_info = $res_enc->fetch_assoc();
            $estado_atual = $enc_info['estado'] ?? '';
            $id_fornecedor = $enc_info['id_fornecedor'] ?? 0;
            $nome_fornecedor = $enc_info['nome_fornecedor'] ?? 'Desconhecido';

            $pode_atualizar = true;
            $n_cab = 0;

            // Se vai transitar para 'recebido' e não estava 'recebido'
            if ($novo_estado === 'recebido' && $estado_atual !== 'recebido') {
                $conn->begin_transaction();
                try {
                    // 1. Criar Movimento de Entrada
                    $max_ent_res = $conn->query("SELECT MAX(n_cab) as max_id FROM ent_cab");
                    $max_sai_res = $conn->query("SELECT MAX(n_cab) as max_id FROM sai_cab");
                    $max_linhas_res = $conn->query("SELECT MAX(id) as max_id FROM linhas");

                    $max_ent = $max_ent_res ? (int) $max_ent_res->fetch_assoc()['max_id'] : 0;
                    $max_sai = $max_sai_res ? (int) $max_sai_res->fetch_assoc()['max_id'] : 0;
                    $max_linhas = $max_linhas_res ? (int) $max_linhas_res->fetch_assoc()['max_id'] : 0;

                    $n_cab = max($max_ent, $max_sai, $max_linhas) + 1;
                    $data_atual = date('Y-m-d H:i:s');

                    $stmt_head = $conn->prepare("INSERT INTO ent_cab (n_cab, cliente, data, id_fornecedor) VALUES (?, ?, ?, ?)");
                    $stmt_head->bind_param("issi", $n_cab, $nome_fornecedor, $data_atual, $id_fornecedor);
                    $stmt_head->execute();
                    $stmt_head->close();

                    // 2. Processar linhas e incrementar stock
                    $n_linha = 1;
                    $res_linhas_detalhes = $conn->query("SELECT efl.id_produto, efl.quantidade, efl.preco_unitario, p.id_categoria 
                                                         FROM encomendas_fornecedores_linhas efl 
                                                         JOIN produtos p ON efl.id_produto = p.id_produto 
                                                         WHERE efl.id_enc_fornecedor = $id_enc_fornecedor");
                    
                    while ($linha_detalhe = $res_linhas_detalhes->fetch_assoc()) {
                        $id_prod = (int)$linha_detalhe['id_produto'];
                        $qtd = (int)$linha_detalhe['quantidade'];
                        $preco = (float)$linha_detalhe['preco_unitario'];
                        $id_cat = (int)$linha_detalhe['id_categoria'];

                        $stmt_line = $conn->prepare("INSERT INTO linhas (id, n_linha, id_produto, id_categoria, descricao, quantidade, preço) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt_line->bind_param("iiiiiid", $n_cab, $n_linha, $id_prod, $id_cat, $id_prod, $qtd, $preco);
                        $stmt_line->execute();
                        $stmt_line->close();

                        // Incrementar stock do produto
                        $upd = $conn->prepare("UPDATE produtos SET quantidade = quantidade + ? WHERE id_produto = ?");
                        $upd->bind_param("ii", $qtd, $id_prod);
                        $upd->execute();
                        $upd->close();

                        $n_linha++;
                    }

                    $stmt_upd = $conn->prepare("UPDATE encomendas_fornecedores SET estado=? WHERE id_enc_fornecedor=?");
                    $stmt_upd->bind_param("si", $novo_estado, $id_enc_fornecedor);
                    $stmt_upd->execute();
                    $stmt_upd->close();

                    $conn->commit();

                    registarLog($_SESSION['id_user'], "REGISTO_MOVIMENTO", "Tipo: ENTRADA, Fornecedor: $nome_fornecedor, Doc: $n_cab, Ref. EncFornecedor: $id_enc_fornecedor");
                    registarLog($_SESSION['id_user'], 'ENC_FORNECEDOR_ESTADO', "EncFornecedor ID: $id_enc_fornecedor - Novo estado: $novo_estado");
                    
                    $success = "Estado atualizado para 'recebido' com sucesso! Stock incrementado no Movimento de Entrada #$n_cab.";
                    $pode_atualizar = false; // Já atualizado dentro da transação
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Erro ao processar receção da encomenda: " . $e->getMessage();
                    $pode_atualizar = false;
                }
            }

            if ($pode_atualizar) {
                $stmt = $conn->prepare("UPDATE encomendas_fornecedores SET estado=? WHERE id_enc_fornecedor=?");
                $stmt->bind_param("si", $novo_estado, $id_enc_fornecedor);
                if ($stmt->execute()) {
                    $success = "Estado atualizado com sucesso!";
                    registarLog($_SESSION['id_user'], 'ENC_FORNECEDOR_ESTADO', "EncFornecedor ID: $id_enc_fornecedor - Novo estado: $novo_estado");
                } else {
                    $error = "Erro ao atualizar estado: " . $conn->error;
                }
            }
        }
    }
}
}

// Buscar encomendas a fornecedores com paginação
$list = new ListManager($conn, 10);
$filtro_sql = $list->getFilterSQL(['ef.num_encomenda', 'f.nome', 'ef.estado'], 'ef');
$list->calculatePagination('encomendas_fornecedores ef LEFT JOIN fornecedores f ON ef.id_fornecedor = f.id_fornecedor', $filtro_sql);
$offset = $list->offset;

$sql = "SELECT ef.*, f.nome as nome_fornecedor 
        FROM encomendas_fornecedores ef 
        LEFT JOIN fornecedores f ON ef.id_fornecedor = f.id_fornecedor 
        $filtro_sql 
        ORDER BY ef.data_encomenda DESC, ef.id_enc_fornecedor DESC 
        LIMIT 10 OFFSET $offset";
$encomendas = $conn->query($sql);

$fornecedores = $conn->query("SELECT id_fornecedor, nome FROM fornecedores ORDER BY nome ASC");
$produtos = $conn->query("
    SELECT id_produto, descricao, preco_unit 
    FROM produtos 
    ORDER BY descricao ASC
");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Encomendas a Fornecedores - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/nav_dropdown.css">
    <style>
        .estado-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-pendente { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .estado-enviado { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .estado-recebido { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .estado-cancelado { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        .encomenda-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .encomenda-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .encomenda-numero {
            font-size: 1.2rem;
            font-weight: bold;
            color: #10b981;
        }
        
        .encomenda-total {
            font-size: 1.1rem;
            color: #10b981;
            font-weight: bold;
        }
        
        .produtos-lista {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .produto-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.9rem;
        }
        
        .input-group {
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 4px !important;
            font-size: 0.85rem;
            color: #ccc;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .produtos-selecionados {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .produto-selecionado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 5px;
            margin-bottom: 5px;
        }
        
        .btn-remover-produto {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
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
                    <button class="nav-btn" onclick="location.href='index.php'" title="Voltar">
                        <i class="fas fa-arrow-left" style="margin:0;"></i>
                    </button>
                    <div>
                        <h2>Encomendas a Fornecedores</h2>
                        <p>Gerir compras e pedidos a fornecedores (sem IVA)</p>
                    </div>
                </div>
                <?php if ($pode_gerir): ?>
                <button class="nav-btn active" onclick="abrirModal()" style="background: linear-gradient(45deg, #10b981, #059669);">
                    <i class="fas fa-plus"></i> Nova Encomenda
                </button>
                <?php endif; ?>
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

            <?php $list->renderSearchBar('Pesquisar encomendas a fornecedores...'); ?>

            <div class="table-container">
                <?php if ($encomendas && $encomendas->num_rows > 0): ?>
                    <?php while ($encomenda = $encomendas->fetch_assoc()): ?>
                        <div class="encomenda-item">
                            <div class="encomenda-header">
                                <div>
                                    <div class="encomenda-numero"><?php echo htmlspecialchars($encomenda['num_encomenda']); ?></div>
                                    <div style="color: #888; font-size: 0.9rem;">
                                        Fornecedor: <?php echo htmlspecialchars($encomenda['nome_fornecedor']); ?> | 
                                        Data: <?php echo date('d/m/Y', strtotime($encomenda['data_encomenda'])); ?>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 12px; align-items: flex-end; text-align: right;">
                                    <span class="estado-badge estado-<?php echo $encomenda['estado']; ?>">
                                        <?php echo $encomenda['estado']; ?>
                                    </span>
                                    <div class="encomenda-total">
                                        <?php echo number_format($encomenda['total_bruto'], 2, ',', '.'); ?> €
                                    </div>
                                </div>
                            </div>
                            
                            <div class="produtos-lista">
                                <?php
                                $prod_sql = "SELECT efl.*, p.descricao 
                                           FROM encomendas_fornecedores_linhas efl 
                                           JOIN produtos p ON efl.id_produto = p.id_produto 
                                           WHERE efl.id_enc_fornecedor = " . $encomenda['id_enc_fornecedor'];
                                $prod_result = $conn->query($prod_sql);
                                if ($prod_result && $prod_result->num_rows > 0):
                                    while ($prod = $prod_result->fetch_assoc()):
                                ?>
                                    <div class="produto-item">
                                        <span><?php echo htmlspecialchars($prod['descricao']); ?></span>
                                        <span>
                                            <?php echo $prod['quantidade']; ?> x <?php echo number_format($prod['preco_unitario'], 2, ',', '.'); ?> € 
                                            = <?php echo number_format($prod['quantidade'] * $prod['preco_unitario'], 2, ',', '.'); ?> €
                                        </span>
                                    </div>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </div>
                            
                            <div style="margin-top: 10px; display: flex; gap: 10px;">
                                <?php if ($pode_gerir): ?>
                                <button class="nav-btn" onclick="editarEncomenda(<?php echo $encomenda['id_enc_fornecedor']; ?>)" style="padding: 8px 15px; font-size: 0.9rem;">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <?php endif; ?>
                                
                                <a href="exportar_pdf_enc_fornecedor.php?id=<?php echo $encomenda['id_enc_fornecedor']; ?>" target="_blank" class="nav-btn" style="padding: 8px 15px; font-size: 0.9rem; background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.4); color: #10b981; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin: 0;">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </a>
                                
                                <?php if ($pode_gerir): ?>
                                <select onchange="atualizarEstado(<?php echo $encomenda['id_enc_fornecedor']; ?>, this.value)" style="padding: 8px; border-radius: 5px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(16,185,129,0.3);">
                                    <option value="">Alterar Estado</option>
                                    <option value="pendente" <?php echo $encomenda['estado'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="enviado" <?php echo $encomenda['estado'] == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                                    <option value="recebido" <?php echo $encomenda['estado'] == 'recebido' ? 'selected' : ''; ?>>Recebido</option>
                                    <option value="cancelado" <?php echo $encomenda['estado'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                                <?php endif; ?>
                                
                                <?php if ($is_admin): ?>
                                    <button class="nav-btn" onclick="eliminarEncomenda(<?php echo $encomenda['id_enc_fornecedor']; ?>)" style="padding: 8px 15px; font-size: 0.9rem; background: rgba(239,68,68,0.2);">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 50px; opacity: 0.5;">
                        <i class="fas fa-truck" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>Sem encomendas a fornecedores registadas.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php $list->renderPagination(); ?>
        </main>
    </div>

    <!-- Modal Encomenda Fornecedor -->
    <div id="encomendaModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close-modal" onclick="fecharModal()">&times;</span>
            <h2 id="modalTitle">Nova Encomenda a Fornecedor</h2>
            <form method="POST" id="encomendaForm">
                <input type="hidden" name="action" id="formAction" value="adicionar">
                <input type="hidden" name="id_enc_fornecedor" id="encomendaId">
                
                <div class="modal-grid">
                    <div class="input-group">
                        <label>Fornecedor *</label>
                        <select name="id_fornecedor" id="fornecedorSelect" required>
                            <option value="">Selecione um fornecedor</option>
                            <?php while ($fornecedor = $fornecedores->fetch_assoc()): ?>
                                <option value="<?php echo $fornecedor['id_fornecedor']; ?>">
                                    <?php echo htmlspecialchars($fornecedor['nome']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Data Encomenda *</label>
                        <input type="date" name="data_encomenda" id="dataEncomenda" required>
                    </div>
                </div>
                
                <div class="input-group full-width">
                    <label>Estado</label>
                    <select name="estado" id="estadoSelect">
                        <option value="pendente">Pendente</option>
                        <option value="enviado">Enviado</option>
                        <option value="recebido">Recebido</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                
                <div class="input-group full-width">
                    <label>Observações</label>
                    <textarea name="observacoes" id="observacoes" rows="3"></textarea>
                </div>
                
                <div class="input-group full-width">
                    <label>Adicionar Produtos</label>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <select id="produtoSelect" style="flex: 1;">
                            <option value="">Selecione um produto</option>
                            <?php 
                            $produtos->data_seek(0);
                            while ($produto = $produtos->fetch_assoc()): 
                                $preco = $produto['preco_unit'];
                            ?>
                                <option value="<?php echo $produto['id_produto']; ?>" data-preco="<?php echo $preco; ?>" data-nome="<?php echo htmlspecialchars($produto['descricao']); ?>">
                                    <?php echo htmlspecialchars($produto['descricao']); ?> (Ref. Venda: <?php echo number_format($preco, 2, ',', '.'); ?> €)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <input type="number" id="quantidadeProduto" placeholder="Qtd" min="1" style="width: 90px;">
                        <input type="number" id="precoProduto" placeholder="Preço Custo (€)" step="0.01" min="0" style="width: 130px;">
                        <button type="button" onclick="adicionarProduto()" class="nav-btn" style="background: linear-gradient(45deg, #10b981, #059669);">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                    
                    <div id="produtosSelecionados" class="produtos-selecionados"></div>
                    
                    <div id="resumoValores" style="margin-top: 15px; padding: 15px; background: rgba(16, 185, 129, 0.05); border-radius: 10px; border: 1px dashed rgba(16, 185, 129, 0.3);">
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.1rem; color: #10b981;">
                            <span>TOTAL (Sem IVA):</span>
                            <span id="resumoTotal">0.00 €</span>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" onclick="fecharModal()" class="nav-btn" style="background: rgba(107, 114, 128, 0.2); margin: 0;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="nav-btn active" style="background: linear-gradient(45deg, #10b981, #059669); margin: 0;">
                        <i class="fas fa-save"></i> Guardar Encomenda
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let produtosArray = [];
        
        // Quando seleciona o produto, preenche o preço sugerido no input de preço
        document.getElementById("produtoSelect").addEventListener("change", function() {
            const selected = this.options[this.selectedIndex];
            if (selected && selected.value) {
                const preco = parseFloat(selected.getAttribute('data-preco') || 0);
                document.getElementById("precoProduto").value = preco.toFixed(2);
            } else {
                document.getElementById("precoProduto").value = "";
            }
        });

        function abrirModal() {
            document.getElementById("modalTitle").innerText = "Nova Encomenda a Fornecedor";
            document.getElementById("formAction").value = "adicionar";
            document.getElementById("encomendaId").value = "";
            document.getElementById("encomendaForm").reset();
            document.getElementById("dataEncomenda").value = new Date().toISOString().split('T')[0];
            produtosArray = [];
            atualizarProdutosSelecionados();
            document.getElementById("encomendaModal").style.display = "flex";
        }
        
        function fecharModal() {
            document.getElementById("encomendaModal").style.display = "none";
        }
        
        function adicionarProduto() {
            const select = document.getElementById("produtoSelect");
            const quantidade = parseInt(document.getElementById("quantidadeProduto").value);
            const precoInput = document.getElementById("precoProduto").value;
            const preco = parseFloat(precoInput);
            
            if (!select.value || !quantidade || quantidade <= 0 || isNaN(preco) || preco < 0) {
                showToast("Selecione um produto, quantidade e preço válidos.", "warning");
                return;
            }
            
            const produto = {
                id_produto: select.value,
                descricao: select.options[select.selectedIndex].getAttribute('data-nome'),
                preco_unitario: preco,
                quantidade: quantidade
            };
            
            produtosArray.push(produto);
            atualizarProdutosSelecionados();
            
            select.value = "";
            document.getElementById("quantidadeProduto").value = "";
            document.getElementById("precoProduto").value = "";
        }
        
        function removerProduto(index) {
            produtosArray.splice(index, 1);
            atualizarProdutosSelecionados();
        }
        
        function atualizarProdutosSelecionados() {
            const container = document.getElementById("produtosSelecionados");
            container.innerHTML = "";
            
            let total = 0;
            
            produtosArray.forEach((produto, index) => {
                const totalLinha = produto.preco_unitario * produto.quantidade;
                total += totalLinha;
                
                const div = document.createElement("div");
                div.className = "produto-selecionado";
                div.innerHTML = `
                    <span>${produto.descricao} - ${produto.quantidade} x ${produto.preco_unitario.toFixed(2)} € = ${totalLinha.toFixed(2)} €</span>
                    <button type="button" onclick="removerProduto(${index})" class="btn-remover-produto">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            });
            
            document.getElementById("resumoTotal").innerText = total.toFixed(2) + " €";
        }
        
        function editarEncomenda(id) {
            fetch('actions/get_enc_fornecedor.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        showToast(data.error, "error");
                        return;
                    }
                    
                    abrirModal();
                    document.getElementById("modalTitle").innerText = "Editar Encomenda a Fornecedor #" + data.num_encomenda;
                    document.getElementById("formAction").value = "editar";
                    document.getElementById("encomendaId").value = id;
                    document.getElementById("fornecedorSelect").value = data.id_fornecedor;
                    document.getElementById("dataEncomenda").value = data.data_encomenda;
                    document.getElementById("estadoSelect").value = data.estado;
                    document.getElementById("observacoes").value = data.observacoes || "";
                    
                    produtosArray = data.produtos.map(p => ({
                        id_produto: p.id_produto,
                        descricao: p.descricao,
                        preco_unitario: parseFloat(p.preco_unitario),
                        quantidade: parseInt(p.quantidade)
                    }));
                    
                    atualizarProdutosSelecionados();
                });
        }
        
        function atualizarEstado(id, estado) {
            if (!estado) return;
            showConfirm("Deseja alterar o estado da encomenda para '" + estado + "'?", () => {
                const form = document.createElement("form");
                form.method = "POST";
                form.innerHTML = `<input type="hidden" name="action" value="atualizar_estado"><input type="hidden" name="id_enc_fornecedor" value="${id}"><input type="hidden" name="novo_estado" value="${estado}">`;
                document.body.appendChild(form);
                form.submit();
            }, "Atualizar Estado", "fa-sync-alt");
        }
        
        function eliminarEncomenda(id) {
            showConfirm("Tem certeza que deseja eliminar esta encomenda a fornecedor?", () => {
                const form = document.createElement("form");
                form.method = "POST";
                form.innerHTML = `<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }, "Eliminar Encomenda", "fa-truck");
        }
        
        document.getElementById("encomendaForm").addEventListener("submit", function(e) {
            e.preventDefault();
            if (produtosArray.length === 0) {
                showToast("Adicione pelo menos um produto à encomenda.", "error");
                return;
            }
            
            const formData = new FormData(this);
            produtosArray.forEach((produto, index) => {
                formData.append(`produtos[${index}][id_produto]`, produto.id_produto);
                formData.append(`produtos[${index}][quantidade]`, produto.quantidade);
                formData.append(`produtos[${index}][preco_unitario]`, produto.preco_unitario);
            });
            
            fetch(window.location.href, {
                method: "POST",
                body: formData
            }).then(() => {
                window.location.reload();
            });
        });
        
        window.onclick = function(event) {
            const modal = document.getElementById("encomendaModal");
            if (event.target == modal) fecharModal();
        }
    </script>
</body>
</html>
