<?php
/**
 * @file promocoes.php
 * @brief Página de gestão de promoções e descontos
 * @author Antigravity
 * @date 2026-05-12
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';
include 'includes/ListManager.php';

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
        $tipo = $_POST['tipo'] ?? '';
        $valor_desconto = $_POST['valor_desconto'] ?? 0;
        $data_inicio = $_POST['data_inicio'] ?? '';
        $data_fim = $_POST['data_fim'] ?? '';
        $codigo_promocional = $_POST['codigo_promocional'] ?? '';
        $minimo_compra = $_POST['minimo_compra'] ?? 0;
        $utilizacoes_maximas = $_POST['utilizacoes_maximas'] ?? 0;
        $aplicar_a = $_POST['aplicar_a'] ?? 'todos';
        $categorias_selecionadas = $_POST['categorias'] ?? [];
        $produtos_selecionados = $_POST['produtos'] ?? [];
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        if (!empty($nome) && !empty($tipo) && !empty($data_inicio) && !empty($data_fim)) {
            if ($action === 'adicionar') {
                $stmt = $conn->prepare("INSERT INTO promocoes (nome, tipo, valor_desconto, data_inicio, data_fim, codigo_promocional, minimo_compra, utilizacoes_maximas, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdsddiii", $nome, $tipo, $valor_desconto, $data_inicio, $data_fim, $codigo_promocional, $minimo_compra, $utilizacoes_maximas, $ativo);
                $stmt->execute();
                $id_promocao = $conn->insert_id;
                
                // Aplicar a categorias/produtos se necessário
                if ($aplicar_a !== 'todos') {
                    if ($aplicar_a === 'categorias') {
                        foreach ($categorias_selecionadas as $id_categoria) {
                            $stmt = $conn->prepare("INSERT INTO promocoes_categorias (id_promocao, id_categoria) VALUES (?, ?)");
                            $stmt->bind_param("ii", $id_promocao, $id_categoria);
                            $stmt->execute();
                        }
                    } elseif ($aplicar_a === 'produtos') {
                        foreach ($produtos_selecionados as $id_produto) {
                            $stmt = $conn->prepare("INSERT INTO promocoes_produtos (id_promocao, id_produto) VALUES (?, ?)");
                            $stmt->bind_param("ii", $id_promocao, $id_produto);
                            $stmt->execute();
                        }
                    }
                }
                
                registarLog($_SESSION['id_user'], 'PROMOCAO_CRIADA', "Promoção: $nome");
                $success = "Promoção criada com sucesso!";
            } elseif ($action === 'editar') {
                $id = $_POST['id'] ?? 0;
                $stmt = $conn->prepare("UPDATE promocoes SET nome=?, tipo=?, valor_desconto=?, data_inicio=?, data_fim=?, codigo_promocional=?, minimo_compra=?, utilizacoes_maximas=?, ativo=? WHERE id_promocao=?");
                $stmt->bind_param("ssdsddiii", $nome, $tipo, $valor_desconto, $data_inicio, $data_fim, $codigo_promocional, $minimo_compra, $utilizacoes_maximas, $ativo, $id);
                $stmt->execute();
                
                // Remover associações antigas
                $stmt = $conn->prepare("DELETE FROM promocoes_categorias WHERE id_promocao=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                $stmt = $conn->prepare("DELETE FROM promocoes_produtos WHERE id_promocao=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                // Adicionar novas associações
                if ($aplicar_a !== 'todos') {
                    if ($aplicar_a === 'categorias') {
                        foreach ($categorias_selecionadas as $id_categoria) {
                            $stmt = $conn->prepare("INSERT INTO promocoes_categorias (id_promocao, id_categoria) VALUES (?, ?)");
                            $stmt->bind_param("ii", $id, $id_categoria);
                            $stmt->execute();
                        }
                    } elseif ($aplicar_a === 'produtos') {
                        foreach ($produtos_selecionados as $id_produto) {
                            $stmt = $conn->prepare("INSERT INTO promocoes_produtos (id_promocao, id_produto) VALUES (?, ?)");
                            $stmt->bind_param("ii", $id, $id_produto);
                            $stmt->execute();
                        }
                    }
                }
                
                registarLog($_SESSION['id_user'], 'PROMOCAO_EDITADA', "Promoção ID: $id");
                $success = "Promoção atualizada com sucesso!";
            }
        } else {
            $error = "Preencha todos os campos obrigatórios.";
        }
    } elseif ($action === 'eliminar') {
        $id = $_POST['id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM promocoes WHERE id_promocao=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Promoção eliminada com sucesso!";
            registarLog($_SESSION['id_user'], 'PROMOCAO_ELIMINADA', "Promoção ID: $id");
        } else {
            $error = "Erro ao eliminar promoção: " . $conn->error;
        }
    }
}

// Buscar promoções com paginação
$list = new ListManager($conn, 10);
$filtro_sql = $list->getFilterSQL(['nome', 'codigo_promocional', 'tipo'], '');
$list->calculatePagination('promocoes', $filtro_sql);
$offset = $list->offset;

$sql = "SELECT p.*, 
               COUNT(DISTINCT pc.id_categoria) as categorias_count,
               COUNT(DISTINCT pp.id_produto) as produtos_count
        FROM promocoes p
        LEFT JOIN promocoes_categorias pc ON p.id_promocao = pc.id_promocao
        LEFT JOIN promocoes_produtos pp ON p.id_promocao = pp.id_promocao
        $filtro_sql
        GROUP BY p.id_promocao
        ORDER BY p.data_criacao DESC
        LIMIT 10 OFFSET $offset";
$promocoes = $conn->query($sql);

// Buscar categorias e produtos para os selects
$categorias = $conn->query("SELECT id_categoria, descricao FROM categoria ORDER BY descricao ASC");
$produtos = $conn->query("SELECT id_produto, descricao FROM produtos ORDER BY descricao ASC");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Promoções - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .promocao-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(188, 111, 241, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .promocao-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .promocao-titulo {
            font-size: 1.3rem;
            font-weight: bold;
            color: #bc6ff1;
            margin-bottom: 5px;
        }
        
        .promocao-tipo {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            background: rgba(188, 111, 241, 0.2);
            color: #bc6ff1;
            text-transform: uppercase;
        }
        
        .promocao-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-ativa { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-inativa { background: rgba(107, 114, 128, 0.2); color: #6b7280; }
        .status-expirada { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        .promocao-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .codigo-promocional {
            background: rgba(188, 111, 241, 0.1);
            padding: 8px 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 1.1rem;
            border: 1px solid rgba(188, 111, 241, 0.3);
        }
        
        .aplicacao-info {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #ccc;
        }
        
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(188, 111, 241, 0.2);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .checkbox-item input {
            margin-right: 8px;
        }
        
        .checkbox-item label {
            color: #ccc;
            cursor: pointer;
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
                        <h2>Gestão de Promoções</h2>
                        <p>Criar e gerir promoções e descontos</p>
                    </div>
                </div>
                <button class="nav-btn active" onclick="abrirModal()">
                    <i class="fas fa-plus"></i> Nova Promoção
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

            <?php $list->renderSearchBar('Pesquisar promoções...'); ?>

            <div class="table-container">
                <?php if ($promocoes && $promocoes->num_rows > 0): ?>
                    <?php while ($promocao = $promocoes->fetch_assoc()): 
                        $hoje = date('Y-m-d');
                        $status = 'inativa';
                        $status_class = 'status-inativa';
                        
                        if ($promocao['ativo']) {
                            if ($hoje >= $promocao['data_inicio'] && $hoje <= $promocao['data_fim']) {
                                $status = 'ativa';
                                $status_class = 'status-ativa';
                            } elseif ($hoje > $promocao['data_fim']) {
                                $status = 'expirada';
                                $status_class = 'status-expirada';
                            }
                        }
                    ?>
                        <div class="promocao-card">
                            <div class="promocao-header">
                                <div>
                                    <div class="promocao-titulo"><?php echo htmlspecialchars($promocao['nome']); ?></div>
                                    <div style="color: #888; font-size: 0.9rem;">
                                        <?php echo date('d/m/Y', strtotime($promocao['data_inicio'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($promocao['data_fim'])); ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="promocao-tipo"><?php echo $promocao['tipo']; ?></span>
                                    <span class="promocao-status <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                </div>
                            </div>
                            
                            <div class="promocao-info">
                                <div class="info-item">
                                    <span class="info-label">Desconto</span>
                                    <span class="info-value">
                                        <?php 
                                        if ($promocao['tipo'] === 'percentagem') {
                                            echo $promocao['valor_desconto'] . '%';
                                        } else {
                                            echo number_format($promocao['valor_desconto'], 2, ',', '.') . ' €';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Mínimo Compra</span>
                                    <span class="info-value"><?php echo number_format($promocao['minimo_compra'], 2, ',', '.') . ' €'; ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Utilizações</span>
                                    <span class="info-value">
                                        <?php 
                                        if ($promocao['utilizacoes_maximas'] == 0) {
                                            echo 'Ilimitadas';
                                        } else {
                                            echo $promocao['utilizacoes_maximas'];
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($promocao['codigo_promocional'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Código</span>
                                    <div class="codigo-promocional"><?php echo htmlspecialchars($promocao['codigo_promocional']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="aplicacao-info">
                                <strong>Aplicado a:</strong> 
                                <?php 
                                if ($promocao['categorias_count'] > 0) {
                                    echo $promocao['categorias_count'] . ' categorias';
                                } elseif ($promocao['produtos_count'] > 0) {
                                    echo $promocao['produtos_count'] . ' produtos';
                                } else {
                                    echo 'Todos os produtos';
                                }
                                ?>
                            </div>
                            
                            <div style="margin-top: 15px; display: flex; gap: 10px;">
                                <button class="nav-btn" onclick="editarPromocao(<?php echo $promocao['id_promocao']; ?>)" style="padding: 8px 15px; font-size: 0.9rem;">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="nav-btn" onclick="eliminarPromocao(<?php echo $promocao['id_promocao']; ?>)" style="padding: 8px 15px; font-size: 0.9rem; background: rgba(239,68,68,0.2);">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 50px; opacity: 0.5;">
                        <i class="fas fa-tags" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>Sem promoções criadas.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php $list->renderPagination(); ?>
        </main>
    </div>

    <!-- Modal Promoção -->
    <div id="promocaoModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <span class="close-modal" onclick="fecharModal()">&times;</span>
            <h2 id="modalTitle">Nova Promoção</h2>
            <form method="POST" id="promocaoForm">
                <input type="hidden" name="action" id="formAction" value="adicionar">
                <input type="hidden" name="id" id="promocaoId">
                
                <div class="modal-grid">
                    <div class="input-group">
                        <label>Nome *</label>
                        <input type="text" name="nome" id="nomePromocao" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Tipo de Desconto *</label>
                        <select name="tipo" id="tipoDesconto" required onchange="toggleValorDesconto()">
                            <option value="percentagem">Percentagem (%)</option>
                            <option value="fixo">Valor Fixo (€)</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Valor do Desconto *</label>
                        <input type="number" name="valor_desconto" id="valorDesconto" step="0.01" min="0" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Código Promocional</label>
                        <input type="text" name="codigo_promocional" id="codigoPromocional" placeholder="Opcional">
                    </div>
                    
                    <div class="input-group">
                        <label>Data de Início *</label>
                        <input type="date" name="data_inicio" id="dataInicio" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Data de Fim *</label>
                        <input type="date" name="data_fim" id="dataFim" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Compra Mínima (€)</label>
                        <input type="number" name="minimo_compra" id="minimoCompra" step="0.01" min="0" value="0">
                    </div>
                    
                    <div class="input-group">
                        <label>Utilizações Máximas</label>
                        <input type="number" name="utilizacoes_maximas" id="utilizacoesMaximas" min="0" value="0">
                        <small style="color: #888;">0 = Ilimitadas</small>
                    </div>
                </div>
                
                <div class="input-group full-width">
                    <label>Aplicar a</label>
                    <select name="aplicar_a" id="aplicarA" onchange="toggleAplicacao()">
                        <option value="todos">Todos os produtos</option>
                        <option value="categorias">Categorias específicas</option>
                        <option value="produtos">Produtos específicos</option>
                    </select>
                </div>
                
                <div id="categoriasContainer" style="display: none;" class="input-group full-width">
                    <label>Selecione as Categorias</label>
                    <div class="checkbox-group">
                        <?php while ($categoria = $categorias->fetch_assoc()): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="categorias[]" value="<?php echo $categoria['id_categoria']; ?>" id="cat_<?php echo $categoria['id_categoria']; ?>">
                                <label for="cat_<?php echo $categoria['id_categoria']; ?>"><?php echo htmlspecialchars($categoria['descricao']); ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <div id="produtosContainer" style="display: none;" class="input-group full-width">
                    <label>Selecione os Produtos</label>
                    <div class="checkbox-group">
                        <?php 
                        // Resetar ponteiro
                        $produtos->data_seek(0);
                        while ($produto = $produtos->fetch_assoc()): 
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="produtos[]" value="<?php echo $produto['id_produto']; ?>" id="prod_<?php echo $produto['id_produto']; ?>">
                                <label for="prod_<?php echo $produto['id_produto']; ?>"><?php echo htmlspecialchars($produto['descricao']); ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <div class="input-group full-width">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="ativo" id="ativoPromocao" checked>
                        <span>Promoção Ativa</span>
                    </label>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" onclick="fecharModal()" class="nav-btn" style="background: rgba(107, 114, 128, 0.2);">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="nav-btn active">
                        <i class="fas fa-save"></i> Guardar Promoção
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() {
            document.getElementById("modalTitle").innerText = "Nova Promoção";
            document.getElementById("formAction").value = "adicionar";
            document.getElementById("promocaoId").value = "";
            document.getElementById("promocaoForm").reset();
            document.getElementById("dataInicio").value = new Date().toISOString().split('T')[0];
            document.getElementById("ativoPromocao").checked = true;
            document.getElementById("categoriasContainer").style.display = "none";
            document.getElementById("produtosContainer").style.display = "none";
            document.getElementById("promocaoModal").style.display = "flex";
        }
        
        function fecharModal() {
            document.getElementById("promocaoModal").style.display = "none";
        }
        
        function toggleValorDesconto() {
            const tipo = document.getElementById("tipoDesconto").value;
            const input = document.getElementById("valorDesconto");
            
            if (tipo === "percentagem") {
                input.max = "100";
                input.placeholder = "Ex: 10 para 10%";
            } else {
                input.max = "";
                input.placeholder = "Ex: 5.50 para €5.50";
            }
        }
        
        function toggleAplicacao() {
            const aplicarA = document.getElementById("aplicarA").value;
            
            document.getElementById("categoriasContainer").style.display = "none";
            document.getElementById("produtosContainer").style.display = "none";
            
            if (aplicarA === "categorias") {
                document.getElementById("categoriasContainer").style.display = "block";
            } else if (aplicarA === "produtos") {
                document.getElementById("produtosContainer").style.display = "block";
            }
        }
        
        function editarPromocao(id) {
            showToast("Funcionalidade de edição em desenvolvimento.", "info");
        }
        
        function eliminarPromocao(id) {
            showConfirm("Tem a certeza que deseja eliminar esta promoção?", () => {
                const form = document.createElement("form");
                form.method = "POST";
                form.innerHTML = `
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }, "Eliminar Promoção", "fa-percentage");
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById("promocaoModal");
            if (event.target == modal) {
                fecharModal();
            }
        }
    </script>
</body>
</html>
