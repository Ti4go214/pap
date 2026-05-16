<?php
/**
 * @file encomendas.php
 * @brief Página de gestão de encomendas/vendas
 * @author Antigravity
 * @date 2026-05-12
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

/**
 * Garante que as colunas necessárias para o desconto na próxima encomenda existem.
 * (Cria-as automaticamente se faltarem, para evitar erros em instalações já existentes.)
 */
function ensureDescontoProximaEncomendaSchema($conn) {
    // clientes.desconto_pendente
    $res = $conn->query("SHOW COLUMNS FROM clientes LIKE 'desconto_pendente'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE clientes ADD COLUMN desconto_pendente TINYINT(1) NOT NULL DEFAULT 0");
    }

    // encomendas.desconto_percent
    $res = $conn->query("SHOW COLUMNS FROM encomendas LIKE 'desconto_percent'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE encomendas ADD COLUMN desconto_percent DECIMAL(5,2) NOT NULL DEFAULT 0");
    }
}

ensureDescontoProximaEncomendaSchema($conn);

// Processar formulário
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'adicionar' || $action === 'editar') {
        $id_cliente = (int)($_POST['id_cliente'] ?? 0);
        $data_encomenda = $_POST['data_encomenda'] ?? date('Y-m-d');
        $estado = $_POST['estado'] ?? 'pendente';
        $observacoes = $_POST['observacoes'] ?? '';
        $produtos = $_POST['produtos'] ?? [];
        
        if ($id_cliente > 0 && !empty($produtos)) {
            $conn->begin_transaction();
            
            try {
                if ($action === 'adicionar') {
                    // Aplicar desconto de fidelização (10%) se o cliente tiver desconto pendente
                    $desconto_percent = 0;
                    $desconto_pendente = 0;

                    $stmt = $conn->prepare("SELECT desconto_pendente FROM clientes WHERE id_cliente=?");
                    $stmt->bind_param("i", $id_cliente);
                    $stmt->execute();
                    $r = $stmt->get_result();
                    if ($r && $r->num_rows > 0) {
                        $desconto_pendente = (int)($r->fetch_assoc()['desconto_pendente'] ?? 0);
                    }
                    if ($desconto_pendente === 1) {
                        $desconto_percent = 10;
                    }

                    // Gerar número de encomenda
                    $num_encomenda = 'EN' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Inserir encomenda
                    $stmt = $conn->prepare("INSERT INTO encomendas (num_encomenda, id_cliente, data_encomenda, estado, observacoes, desconto_percent) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sisssd", $num_encomenda, $id_cliente, $data_encomenda, $estado, $observacoes, $desconto_percent);
                    $stmt->execute();
                    $id_encomenda = $conn->insert_id;

                    // Consumir desconto pendente (se foi aplicado nesta encomenda)
                    if ($desconto_percent > 0) {
                        $stmt = $conn->prepare("UPDATE clientes SET desconto_pendente=0 WHERE id_cliente=?");
                        $stmt->bind_param("i", $id_cliente);
                        $stmt->execute();
                    }
                    
                    registarLog($_SESSION['id_user'], 'ENCOMENDA_CRIADA', "Encomenda: $num_encomenda");
                } elseif ($action === 'editar') {
                    $id_encomenda = $_POST['id_encomenda'] ?? 0;
                    $stmt = $conn->prepare("UPDATE encomendas SET id_cliente=?, data_encomenda=?, estado=?, observacoes=? WHERE id_encomenda=?");
                    $stmt->bind_param("isssi", $id_cliente, $data_encomenda, $estado, $observacoes, $id_encomenda);
                    $stmt->execute();
                    
                    // Remover produtos antigos
                    $stmt = $conn->prepare("DELETE FROM encomendas_linhas WHERE id_encomenda=?");
                    $stmt->bind_param("i", $id_encomenda);
                    $stmt->execute();
                    
                    registarLog($_SESSION['id_user'], 'ENCOMENDA_EDITADA', "Encomenda ID: $id_encomenda");
                }
                
                // Inserir produtos da encomenda
                foreach ($produtos as $produto) {
                    $id_produto = $produto['id_produto'];
                    $quantidade = $produto['quantidade'];
                    $preco_unitario = $produto['preco_unitario'];
                    
                    $stmt = $conn->prepare("INSERT INTO encomendas_linhas (id_encomenda, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiid", $id_encomenda, $id_produto, $quantidade, $preco_unitario);
                    $stmt->execute();
                }
                
                $conn->commit();
                $success = ($action === 'adicionar') ? "Encomenda criada com sucesso!" : "Encomenda atualizada com sucesso!";
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Erro: " . $e->getMessage();
            }
        } else {
            $error = "Preencha todos os campos obrigatórios.";
        }
    } elseif ($action === 'eliminar') {
        $id = $_POST['id'] ?? 0;
        if ($is_admin) {
            $stmt = $conn->prepare("DELETE FROM encomendas WHERE id_encomenda=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Encomenda eliminada com sucesso!";
                registarLog($_SESSION['id_user'], 'ENCOMENDA_ELIMINADA', "Encomenda ID: $id");
            } else {
                $error = "Erro ao eliminar encomenda: " . $conn->error;
            }
        } else {
            $error = "Apenas administradores podem eliminar encomendas.";
        }
    } elseif ($action === 'atualizar_estado') {
        $id_encomenda = $_POST['id_encomenda'] ?? 0;
        $novo_estado = $_POST['novo_estado'] ?? '';
        
        if (!empty($novo_estado)) {
            $stmt = $conn->prepare("UPDATE encomendas SET estado=? WHERE id_encomenda=?");
            $stmt->bind_param("si", $novo_estado, $id_encomenda);
            if ($stmt->execute()) {
                $success = "Estado atualizado com sucesso!";
                registarLog($_SESSION['id_user'], 'ENCOMENDA_ESTADO', "Encomenda ID: $id_encomenda - Novo estado: $novo_estado");

                // Regra: quando a encomenda fica "entregue", o cliente ganha desconto na próxima encomenda
                if ($novo_estado === 'entregue') {
                    $res = $conn->query("SELECT id_cliente FROM encomendas WHERE id_encomenda = " . (int)$id_encomenda . " LIMIT 1");
                    if ($res && $res->num_rows > 0) {
                        $id_cliente_desconto = (int)$res->fetch_assoc()['id_cliente'];
                        $conn->query("UPDATE clientes SET desconto_pendente=1 WHERE id_cliente=" . $id_cliente_desconto);
                    }
                }
            } else {
                $error = "Erro ao atualizar estado: " . $conn->error;
            }
        }
    }
}

// Buscar encomendas com paginação
$list = new ListManager($conn, 10); // 10 encomendas por página
$filtro_sql = $list->getFilterSQL(['e.num_encomenda', 'c.nome', 'e.estado'], 'e');
$list->calculatePagination('encomendas e LEFT JOIN clientes c ON e.id_cliente = c.id_cliente', $filtro_sql);
$offset = $list->offset;

$sql = "SELECT e.*, c.nome as nome_cliente 
        FROM encomendas e 
        LEFT JOIN clientes c ON e.id_cliente = c.id_cliente 
        $filtro_sql 
        ORDER BY e.data_encomenda DESC 
        LIMIT 10 OFFSET $offset";
$encomendas = $conn->query($sql);

// Buscar clientes para dropdown
$clientes = $conn->query("SELECT id_cliente, nome FROM clientes ORDER BY nome ASC");
$produtos = $conn->query("SELECT id_produto, descricao, preco_unit, quantidade FROM produtos WHERE quantidade > 0 ORDER BY descricao ASC");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Encomendas - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .estado-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-pendente { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .estado-processamento { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .estado-enviado { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .estado-entregue { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .estado-cancelado { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        .encomenda-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(188, 111, 241, 0.1);
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
            color: #bc6ff1;
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
        
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .produtos-selecionados {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(188, 111, 241, 0.2);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .produto-selecionado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            background: rgba(188, 111, 241, 0.1);
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
                        <h2>Gestão de Encomendas</h2>
                        <p>Gerir encomendas e vendas</p>
                    </div>
                </div>
                <button class="nav-btn active" onclick="abrirModal()">
                    <i class="fas fa-plus"></i> Nova Encomenda
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

            <?php $list->renderSearchBar('Pesquisar encomendas...'); ?>

            <div class="table-container">
                <?php if ($encomendas && $encomendas->num_rows > 0): ?>
                    <?php while ($encomenda = $encomendas->fetch_assoc()): ?>
                        <div class="encomenda-item">
                            <div class="encomenda-header">
                                <div>
                                    <div class="encomenda-numero"><?php echo htmlspecialchars($encomenda['num_encomenda']); ?></div>
                                    <div style="color: #888; font-size: 0.9rem;">
                                        Cliente: <?php echo htmlspecialchars($encomenda['nome_cliente']); ?> | 
                                        Data: <?php echo date('d/m/Y', strtotime($encomenda['data_encomenda'])); ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="estado-badge estado-<?php echo $encomenda['estado']; ?>">
                                        <?php echo $encomenda['estado']; ?>
                                    </span>
                                    <div class="encomenda-total">
                                        <?php 
                                        // Calcular total da encomenda
                                        $total_sql = "SELECT SUM(quantidade * preco_unitario) as total 
                                                   FROM encomendas_linhas 
                                                   WHERE id_encomenda = " . $encomenda['id_encomenda'];
                                        $total_result = $conn->query($total_sql);
                                        $total_bruto = (float)($total_result->fetch_assoc()['total'] ?? 0);
                                        $desconto_percent = (float)($encomenda['desconto_percent'] ?? 0);
                                        $total_liquido = $total_bruto;
                                        if ($desconto_percent > 0) {
                                            $total_liquido = $total_bruto * (1 - ($desconto_percent / 100));
                                        }
                                        echo number_format($total_liquido, 2, ',', '.'); ?> €
                                        <?php if ($desconto_percent > 0): ?>
                                            <div style="font-size: 0.75rem; color: #888; margin-top: 2px;">
                                                Desconto fidelização: <?php echo number_format($desconto_percent, 0); ?>%
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="produtos-lista">
                                <?php
                                $prod_sql = "SELECT el.*, p.descricao 
                                           FROM encomendas_linhas el 
                                           JOIN produtos p ON el.id_produto = p.id_produto 
                                           WHERE el.id_encomenda = " . $encomenda['id_encomenda'];
                                $prod_result = $conn->query($prod_sql);
                                if ($prod_result && $prod_result->num_rows > 0):
                                    while ($prod = $prod_result->fetch_assoc()):
                                ?>
                                    <div class="produto-item">
                                        <span><?php echo htmlspecialchars($prod['descricao']); ?></span>
                                        <span><?php echo $prod['quantidade']; ?> x <?php echo number_format($prod['preco_unitario'], 2, ',', '.'); ?> € = <?php echo number_format($prod['quantidade'] * $prod['preco_unitario'], 2, ',', '.'); ?> €</span>
                                    </div>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </div>
                            
                            <div style="margin-top: 10px; display: flex; gap: 10px;">
                                <button class="nav-btn" onclick="editarEncomenda(<?php echo $encomenda['id_encomenda']; ?>)" style="padding: 8px 15px; font-size: 0.9rem;">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                
                                <select onchange="atualizarEstado(<?php echo $encomenda['id_encomenda']; ?>, this.value)" style="padding: 8px; border-radius: 5px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(188,111,241,0.3);">
                                    <option value="">Alterar Estado</option>
                                    <option value="pendente" <?php echo $encomenda['estado'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="processamento" <?php echo $encomenda['estado'] == 'processamento' ? 'selected' : ''; ?>>Em Processamento</option>
                                    <option value="enviado" <?php echo $encomenda['estado'] == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                                    <option value="entregue" <?php echo $encomenda['estado'] == 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                                    <option value="cancelado" <?php echo $encomenda['estado'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                                
                                <?php if ($is_admin): ?>
                                    <button class="nav-btn" onclick="eliminarEncomenda(<?php echo $encomenda['id_encomenda']; ?>)" style="padding: 8px 15px; font-size: 0.9rem; background: rgba(239,68,68,0.2);">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 50px; opacity: 0.5;">
                        <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>Sem encomendas registadas.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php $list->renderPagination(); ?>
        </main>
    </div>

    <!-- Modal Encomenda -->
    <div id="encomendaModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close-modal" onclick="fecharModal()">&times;</span>
            <h2 id="modalTitle">Nova Encomenda</h2>
            <form method="POST" id="encomendaForm">
                <input type="hidden" name="action" id="formAction" value="adicionar">
                <input type="hidden" name="id_encomenda" id="encomendaId">
                
                <div class="modal-grid">
                    <div class="input-group">
                        <label>Cliente *</label>
                        <select name="id_cliente" id="clienteSelect" required>
                            <option value="">Selecione um cliente</option>
                            <?php while ($cliente = $clientes->fetch_assoc()): ?>
                                <option value="<?php echo $cliente['id_cliente']; ?>"><?php echo htmlspecialchars($cliente['nome']); ?></option>
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
                        <option value="processamento">Em Processamento</option>
                        <option value="enviado">Enviado</option>
                        <option value="entregue">Entregue</option>
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
                            // Resetar ponteiro do resultado
                            $produtos->data_seek(0);
                            while ($produto = $produtos->fetch_assoc()): 
                                $preco = $produto['preco_unit'];
                            ?>
                                <option value="<?php echo $produto['id_produto']; ?>" data-preco="<?php echo $preco; ?>" data-nome="<?php echo htmlspecialchars($produto['descricao']); ?>">
                                    <?php echo htmlspecialchars($produto['descricao']); ?> - Stock: <?php echo (int)($produto['quantidade'] ?? 0); ?> - <?php echo number_format($preco, 2, ',', '.'); ?> €
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <input type="number" id="quantidadeProduto" placeholder="Quantidade" min="1" style="width: 120px;">
                        <button type="button" onclick="adicionarProduto()" class="nav-btn">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                    
                    <div id="produtosSelecionados" class="produtos-selecionados"></div>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" onclick="fecharModal()" class="nav-btn" style="background: rgba(107, 114, 128, 0.2);">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="nav-btn active">
                        <i class="fas fa-save"></i> Guardar Encomenda
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let produtosArray = [];
        
        function abrirModal() {
            document.getElementById("modalTitle").innerText = "Nova Encomenda";
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
            
            if (!select.value || !quantidade || quantidade <= 0) {
                alert("Selecione um produto e quantidade válida.");
                return;
            }
            
            const produto = {
                id_produto: select.value,
                descricao: select.options[select.selectedIndex].getAttribute('data-nome'),
                preco_unitario: parseFloat(select.options[select.selectedIndex].getAttribute('data-preco')),
                quantidade: quantidade
            };
            
            produtosArray.push(produto);
            atualizarProdutosSelecionados();
            
            // Limpar campos
            select.value = "";
            document.getElementById("quantidadeProduto").value = "";
        }
        
        function removerProduto(index) {
            produtosArray.splice(index, 1);
            atualizarProdutosSelecionados();
        }
        
        function atualizarProdutosSelecionados() {
            const container = document.getElementById("produtosSelecionados");
            container.innerHTML = "";
            
            produtosArray.forEach((produto, index) => {
                const div = document.createElement("div");
                div.className = "produto-selecionado";
                div.innerHTML = `
                    <span>${produto.descricao} - ${produto.quantidade} x ${produto.preco_unitario.toFixed(2)} € = ${(produto.quantidade * produto.preco_unitario).toFixed(2)} €</span>
                    <button type="button" onclick="removerProduto(${index})" class="btn-remover-produto">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            });
        }
        
        function editarEncomenda(id) {
            // Aqui poderia carregar os dados da encomenda para edição
            alert("Funcionalidade de edição em desenvolvimento.");
        }
        
        function atualizarEstado(id, estado) {
            if (!estado) return;
            
            if (confirm("Deseja alterar o estado da encomenda para '" + estado + "'?")) {
                const form = document.createElement("form");
                form.method = "POST";
                form.innerHTML = `
                    <input type="hidden" name="action" value="atualizar_estado">
                    <input type="hidden" name="id_encomenda" value="${id}">
                    <input type="hidden" name="novo_estado" value="${estado}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function eliminarEncomenda(id) {
            if (confirm("Tem certeza que deseja eliminar esta encomenda?")) {
                const form = document.createElement("form");
                form.method = "POST";
                form.innerHTML = `
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Adicionar produtos ao formulário antes de submeter
        document.getElementById("encomendaForm").addEventListener("submit", function(e) {
            e.preventDefault();
            
            if (produtosArray.length === 0) {
                alert("Adicione pelo menos um produto à encomenda.");
                return;
            }
            
            const formData = new FormData(this);
            
            // Adicionar produtos
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
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById("encomendaModal");
            if (event.target == modal) {
                fecharModal();
            }
        }
    </script>
</body>
</html>
