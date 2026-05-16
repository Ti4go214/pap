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

// Configurações globais
$currency = "€";
$stock_limit = 5; // Limite para alerta visual de stock baixo

// Sistema de Filtros Avançados
$filtros = [];
$where_clauses = [];
$order_by = "p.descricao ASC";

// Processar filtros GET
if (isset($_GET['categoria']) && !empty($_GET['categoria'])) {
    $filtros['categoria'] = $_GET['categoria'];
    $where_clauses[] = "p.id_categoria = '" . $conn->real_escape_string($_GET['categoria']) . "'";
}

if (isset($_GET['preco_min']) && !empty($_GET['preco_min'])) {
    $filtros['preco_min'] = floatval($_GET['preco_min']);
    $where_clauses[] = "p.preco_unit >= " . $filtros['preco_min'];
}

if (isset($_GET['preco_max']) && !empty($_GET['preco_max'])) {
    $filtros['preco_max'] = floatval($_GET['preco_max']);
    $where_clauses[] = "p.preco_unit <= " . $filtros['preco_max'];
}

if (isset($_GET['quantidade_min']) && !empty($_GET['quantidade_min'])) {
    $filtros['quantidade_min'] = intval($_GET['quantidade_min']);
    $where_clauses[] = "p.quantidade >= " . $filtros['quantidade_min'];
}

if (isset($_GET['quantidade_max']) && !empty($_GET['quantidade_max'])) {
    $filtros['quantidade_max'] = intval($_GET['quantidade_max']);
    $where_clauses[] = "p.quantidade <= " . $filtros['quantidade_max'];
}

if (isset($_GET['stock_status'])) {
    $filtros['stock_status'] = $_GET['stock_status'];
    switch ($_GET['stock_status']) {
        case 'low':
            $where_clauses[] = "p.quantidade <= $stock_limit";
            break;
        case 'normal':
            $where_clauses[] = "p.quantidade > $stock_limit";
            break;
        case 'out':
            $where_clauses[] = "p.quantidade = 0";
            break;
    }
}

if (isset($_GET['ordenar']) && !empty($_GET['ordenar'])) {
    $filtros['ordenar'] = $_GET['ordenar'];
    switch ($_GET['ordenar']) {
        case 'preco_asc':
            $order_by = "p.preco_unit ASC";
            break;
        case 'preco_desc':
            $order_by = "p.preco_unit DESC";
            break;
        case 'quantidade_asc':
            $order_by = "p.quantidade ASC";
            break;
        case 'quantidade_desc':
            $order_by = "p.quantidade DESC";
            break;
        case 'nome_asc':
            $order_by = "p.descricao ASC";
            break;
        case 'nome_desc':
            $order_by = "p.descricao DESC";
            break;
        case 'id_asc':
            $order_by = "p.id_produto ASC";
            break;
        case 'id_desc':
            $order_by = "p.id_produto DESC";
            break;
    }
}

// Construir cláusula WHERE
$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Usar ListManager com filtros personalizados
$list = new ListManager($conn, 8); // 8 items por página

// Sessão do Utilizador
$utilizador_id = $_SESSION["utilizador_id"] ?? '';
$utilizador_nome = $_SESSION["utilizador_nome"] ?? '';

// Lógica via Classe - combinar com filtros personalizados
$filtro_sql = $list->getFilterSQL(['descricao', 'id_produto'], 'p');
if (!empty($filtro_sql)) {
    if (!empty($where_sql)) {
        $where_sql .= " AND " . substr($filtro_sql, 6); // Remove "WHERE " e adiciona " AND "
    } else {
        $where_sql = $filtro_sql;
    }
}

$list->calculatePagination('produtos p', $where_sql);
$offset = $list->offset; // Aceder ao offset para a query principal

// Obter Produtos com Nome da Categoria
$sql = "SELECT p.*, c.descricao as categoria_nome 
        FROM produtos p 
        LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
        $where_sql
        ORDER BY $order_by
        LIMIT 8 OFFSET $offset";

$resultado = $conn->query($sql);

// Obter Categorias para Dropdown do Modal
$cats = $conn->query("SELECT * FROM categoria");
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Gestão de Stock - TSTORE</title>
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
                <div>
                    <h2>Gestão de Stock</h2>
                    <p>Total de produtos: <strong>
                            <?php echo $list->totalRecords; ?>
                        </strong></p>
                </div>
                <div class="header-actions" style="display: flex; gap: 15px;">
                    <button class="nav-btn" onclick="location.href='actions/export_actions.php?type=stock'" 
                            style="border-color: rgba(16, 185, 129, 0.4); color: #10b981; background: rgba(16, 185, 129, 0.05);">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </button>
                    <?php if (($_SESSION['role'] ?? 1) == 0 || ($_SESSION['role'] ?? 1) == 2): ?>
                        <button class="nav-btn active" onclick="abrirModalStock()">
                            <i class="fas fa-plus"></i> Novo Produto
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <!-- PAINEL DE FILTROS -->
            <div class="filters-panel" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(188, 111, 241, 0.1); border-radius: 15px; padding: 20px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="color: #bc6ff1; margin: 0; font-size: 1.1rem;">
                        <i class="fas fa-filter"></i> Filtros Avançados
                    </h3>
                    <button onclick="toggleFilters()" style="background: transparent; border: 1px solid #bc6ff1; color: #bc6ff1; padding: 5px 15px; border-radius: 20px; cursor: pointer; font-size: 0.8rem;">
                        <i class="fas fa-chevron-down" id="filterToggleIcon"></i> Expandir
                    </button>
                </div>
                
                <form id="filtersForm" method="GET" style="display: none;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <!-- Filtro de Categoria -->
                        <div>
                            <label style="display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9rem;">Categoria</label>
                            <select name="categoria" class="full-width">
                                <option value="">Todas as categorias</option>
                                <?php
                                if ($cats) {
                                    $cats->data_seek(0);
                                    while ($c = $cats->fetch_assoc()) {
                                        $selected = (isset($filtros['categoria']) && $filtros['categoria'] == $c['id_categoria']) ? 'selected' : '';
                                        echo "<option value='" . $c['id_categoria'] . "' $selected>" . $c['descricao'] . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Filtro de Preço -->
                        <div>
                            <label style="display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9rem;">Preço Mínimo (€)</label>
                            <input type="number" name="preco_min" step="0.01" min="0" placeholder="0.00" 
                                   value="<?php echo $filtros['preco_min'] ?? ''; ?>"
                                   class="full-width">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9rem;">Preço Máximo (€)</label>
                            <input type="number" name="preco_max" step="0.01" min="0" placeholder="999.99"
                                   value="<?php echo $filtros['preco_max'] ?? ''; ?>"
                                   class="full-width">
                        </div>

                        <!-- Filtro de Quantidade -->
                        <div>
                            <label style="display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9rem;">Quantidade Mínima</label>
                            <input type="number" name="quantidade_min" min="0" placeholder="0"
                                   value="<?php echo $filtros['quantidade_min'] ?? ''; ?>"
                                   class="full-width">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9rem;">Quantidade Máxima</label>
                            <input type="number" name="quantidade_max" min="0" placeholder="999"
                                   value="<?php echo $filtros['quantidade_max'] ?? ''; ?>"
                                   class="full-width">
                        </div>

                        <!-- Filtro de Status de Stock -->
                        <div>
                            <label style="display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9rem;">Status de Stock</label>
                            <select name="stock_status" class="full-width">
                                <option value="">Todos</option>
                                <option value="low" <?php echo (isset($filtros['stock_status']) && $filtros['stock_status'] == 'low') ? 'selected' : ''; ?>>Stock Baixo (≤ <?php echo $stock_limit; ?>)</option>
                                <option value="normal" <?php echo (isset($filtros['stock_status']) && $filtros['stock_status'] == 'normal') ? 'selected' : ''; ?>>Stock Normal</option>
                                <option value="out" <?php echo (isset($filtros['stock_status']) && $filtros['stock_status'] == 'out') ? 'selected' : ''; ?>>Sem Stock</option>
                            </select>
                        </div>

                        <!-- Ordenação -->
                        <div>
                            <label style="display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9rem;">Ordenar por</label>
                            <select name="ordenar" class="full-width">
                                <option value="nome_asc" <?php echo (isset($filtros['ordenar']) && $filtros['ordenar'] == 'nome_asc') ? 'selected' : ''; ?>>Nome (A-Z)</option>
                                <option value="nome_desc" <?php echo (isset($filtros['ordenar']) && $filtros['ordenar'] == 'nome_desc') ? 'selected' : ''; ?>>Nome (Z-A)</option>
                                <option value="preco_asc" <?php echo (isset($filtros['ordenar']) && $filtros['ordenar'] == 'preco_asc') ? 'selected' : ''; ?>>Preço (Menor-Maior)</option>
                                <option value="preco_desc" <?php echo (isset($filtros['ordenar']) && $filtros['ordenar'] == 'preco_desc') ? 'selected' : ''; ?>>Preço (Maior-Menor)</option>
                                <option value="quantidade_asc" <?php echo (isset($filtros['ordenar']) && $filtros['ordenar'] == 'quantidade_asc') ? 'selected' : ''; ?>>Quantidade (Menor-Maior)</option>
                                <option value="quantidade_desc" <?php echo (isset($filtros['ordenar']) && $filtros['ordenar'] == 'quantidade_desc') ? 'selected' : ''; ?>>Quantidade (Maior-Menor)</option>
                                <option value="id_asc" <?php echo (isset($filtros['ordenar']) && $filtros['ordenar'] == 'id_asc') ? 'selected' : ''; ?>>ID (Crescente)</option>
                                <option value="id_desc" <?php echo (isset($filtros['ordenar']) && $filtros['ordenar'] == 'id_desc') ? 'selected' : ''; ?>>ID (Decrescente)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <button type="submit" class="nav-btn active" style="padding: 8px 20px;">
                            <i class="fas fa-search"></i> Aplicar Filtros
                        </button>
                        <button type="button" onclick="clearFilters()" class="nav-btn" style="background: transparent; border-color: #ff4b2b; color: #ff4b2b; padding: 8px 20px;">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                    </div>
                </form>
                
                <!-- Filtros Ativos -->
                <?php if (!empty($filtros)): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                        <div style="color: #aaa; font-size: 0.8rem; margin-bottom: 8px;">Filtros ativos:</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php
                            $filter_labels = [
                                'categoria' => 'Categoria',
                                'preco_min' => 'Preço Mínimo',
                                'preco_max' => 'Preço Máximo',
                                'quantidade_min' => 'Qtd. Mínima',
                                'quantidade_max' => 'Qtd. Máxima',
                                'stock_status' => 'Status Stock',
                                'ordenar' => 'Ordenação'
                            ];
                            
                            foreach ($filtros as $key => $value) {
                                if (!empty($value)) {
                                    echo "<span style='background: rgba(188, 111, 241, 0.1); color: #bc6ff1; padding: 4px 8px; border-radius: 12px; font-size: 0.7rem;'>";
                                    echo $filter_labels[$key] ?? $key;
                                    echo "</span>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php $list->renderSearchBar("Pesquisar produto..."); ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                            <th>Imagem</th>
                            <th>ID</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Preço (€)</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($resultado && $resultado->num_rows > 0) {
                            while ($row = $resultado->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td data-label="Selecionar"><input type="checkbox" class="product-checkbox" value="<?php echo $row['id_produto']; ?>" onclick="updateBulkBar()"></td>
                                    <td data-label="Imagem">
                                        <div class="product-thumb">
                                            <img src="assets/img/products/<?php echo isset($row['imagem']) ? $row['imagem'] : 'default_product.png'; ?>" 
                                                 alt="Product" onerror="this.onerror=null; this.src='assets/img/products/default_product.png'">
                                        </div>
                                    </td>
                                    <td data-label="ID"><strong>#
                                            <?php echo htmlspecialchars($row["id_produto"]); ?>
                                        </strong></td>
                                    <td data-label="Descrição">
                                        <?php echo htmlspecialchars($row["descricao"]); ?>
                                    </td>
                                    <td data-label="Categoria"><span
                                            style="padding: 5px 10px; background: rgba(188, 111, 241, 0.1); border-radius: 5px; color: #bc6ff1; font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($row["categoria_nome"] ?? 'N/A'); ?>
                                        </span></td>
                                    <td data-label="Quantidade">
                                        <?php if ($row["quantidade"] <= $stock_limit): ?>
                                            <span style="color: #ff4b2b; font-weight: bold;"><i
                                                    class="fas fa-circle-exclamation"></i>
                                                <?php echo $row["quantidade"]; ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo $row["quantidade"]; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Preço">
                                        <?php echo number_format($row["preco_unit"], 2, ',', ' '); ?> <?php echo $currency; ?>
                                    </td>
                                    <td data-label="Ações">
                                        <?php if (($_SESSION['role'] ?? 1) == 0 || ($_SESSION['role'] ?? 1) == 2): ?>
                                            <div class="action-btns">
                                                <button
                                                    onclick="editarModalStock('<?php echo $row['id_produto']; ?>', '<?php echo htmlspecialchars(addslashes($row['descricao'])); ?>', '<?php echo $row['id_categoria']; ?>', '<?php echo $row['quantidade']; ?>', '<?php echo $row['preco_unit']; ?>', '<?php echo isset($row['imagem']) ? $row['imagem'] : 'default_product.png'; ?>')"
                                                    class="edit-btn" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button
                                                    onclick="eliminarStock('<?php echo $row['id_produto']; ?>', '<?php echo htmlspecialchars(addslashes($row['descricao'])); ?>')"
                                                    class="delete-btn" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="opacity: 0.5; font-size: 0.8rem;">Sem permissão</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 50px; opacity: 0.5;'>Nenhum produto encontrado no inventário.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação via Classe -->
            <?php $list->renderPagination(); ?>

        </main>
    </div>

    <!-- MODAL DE STOCK -->
    <div id="modalStock" class="modal">
        <div class="modal-content"
            style="background: #1e0f32; padding: 20px; border-radius: 10px; width: 100%; max-width: 600px;">
            <span onclick="fecharModalStock()"
                style="cursor:pointer; float:right; color:white; font-size: 1.5rem;">&times;</span>
            <h2 id="modalTitleStock" style="color: #bc6ff1; margin-bottom: 20px;">Novo Produto</h2>

            <form action="actions/stock_actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_produto" id="s_id">

                <div class="form-grid">
                    <div class="input-group full-width">
                        <label>Nome do Produto</label>
                        <input type="text" name="descricao" id="s_descricao" required
                            placeholder="Ex: Teclado Mecânico...">
                    </div>

                    <div class="input-group">
                        <label>Categoria</label>
                        <select name="categoria" id="s_categoria"
                            class="full-width">
                            <?php
                            if ($cats) {
                                $cats->data_seek(0); // Reiniciar ponteiro
                                while ($c = $cats->fetch_assoc()) {
                                    echo "<option value='" . $c['id_categoria'] . "'>" . $c['descricao'] . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Quantidade</label>
                        <input type="number" name="quantidade" id="s_quantidade" required min="0">
                    </div>

                    <div class="input-group">
                        <label>Preço Unitário (<?php echo $currency; ?>)</label>
                        <input type="number" name="preco" id="s_preco" required step="0.01" min="0">
                    </div>

                    <div class="input-group full-width">
                        <label>Imagem do Produto</label>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div id="imagePreview" style="width: 60px; height: 60px; border-radius: 8px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);">
                                <i class="fas fa-image" style="opacity: 0.3;"></i>
                            </div>
                            <input type="file" name="imagem" id="s_imagem" accept="image/*" onchange="previewImage(this)" style="flex: 1;">
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="button" onclick="fecharModalStock()" class="nav-btn"
                        style="background: transparent; border-color: #ff4b2b; color: #ff4b2b;">Cancelar</button>
                    <button type="submit" name="btn_save_stock" class="nav-btn active">
                        <i class="fas fa-save"></i> Gravar Produto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/stock.js"></script>

<script>
// Funções para controlo dos filtros
function toggleFilters() {
    const form = document.getElementById('filtersForm');
    const icon = document.getElementById('filterToggleIcon');
    const button = event.target.closest('button');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        icon.className = 'fas fa-chevron-up';
        button.innerHTML = '<i class="fas fa-chevron-up" id="filterToggleIcon"></i> Recolher';
        
        // Animar expansão
        form.style.opacity = '0';
        form.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            form.style.transition = 'all 0.3s ease';
            form.style.opacity = '1';
            form.style.transform = 'translateY(0)';
        }, 10);
    } else {
        form.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
        button.innerHTML = '<i class="fas fa-chevron-down" id="filterToggleIcon"></i> Expandir';
    }
}

function clearFilters() {
    // Limpar todos os campos do formulário
    const form = document.getElementById('filtersForm');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        if (input.type === 'number' || input.type === 'text') {
            input.value = '';
        } else if (input.type === 'select-one') {
            input.selectedIndex = 0;
        }
    });
    
    // Submeter formulário limpo
    form.submit();
}

// Aplicar filtros automaticamente quando alterados (opcional)
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar painel de filtros se houver filtros ativos
    <?php if (!empty($filtros)): ?>
        toggleFilters();
    <?php endif; ?>
    
    // Adicionar evento de change para aplicação automática (opcional)
    const filterInputs = document.querySelectorAll('#filtersForm input, #filtersForm select');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Se quiser aplicação automática, descomente a linha abaixo
            // document.getElementById('filtersForm').submit();
        });
    });
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
</script>

<style>
    .product-thumb { width: 40px; height: 40px; border-radius: 8px; overflow: hidden; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
    .product-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .bulk-actions-bar { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #1e0f32; border: 1px solid #bc6ff1; padding: 15px 30px; border-radius: 50px; display: none; gap: 20px; align-items: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 1000; animation: slideUp 0.3s ease; }
    @keyframes slideUp { from { bottom: -100px; opacity: 0; } to { bottom: 30px; opacity: 1; } }
</style>

<div class="bulk-actions-bar" id="bulkBar">
    <span id="selectedCount" style="color: #fff; font-weight: bold;">0 itens selecionados</span>
    <button class="nav-btn" onclick="bulkDelete()" style="background: rgba(255, 75, 43, 0.1); border-color: #ff4b2b; color: #ff4b2b;">
        <i class="fas fa-trash"></i> Eliminar
    </button>
    <button class="nav-btn" onclick="bulkExport()">
        <i class="fas fa-file-excel"></i> Exportar
    </button>
</div>

<script>
    function toggleSelectAll(source) {
        checkboxes = document.getElementsByClassName('product-checkbox');
        for(var i=0, n=checkboxes.length; i<n; i++) {
            checkboxes[i].checked = source.checked;
        }
        updateBulkBar();
    }

    function updateBulkBar() {
        const selected = document.querySelectorAll('.product-checkbox:checked').length;
        const bar = document.getElementById('bulkBar');
        const count = document.getElementById('selectedCount');
        
        if (selected > 0) {
            bar.style.display = 'flex';
            count.innerText = selected + ' item(ns) selecionado(s)';
        } else {
            bar.style.display = 'none';
        }
    }

    function bulkDelete() {
        const ids = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
        if (confirm('Tem a certeza que deseja eliminar ' + ids.length + ' produtos?')) {
            const formData = new FormData();
            formData.append('ids', JSON.stringify(ids));
            formData.append('action', 'bulk_delete');
            
            fetch('actions/bulk_actions.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(data => {
                if (data.success) location.reload();
                else alert('Erro ao eliminar produtos: ' + (data.message || 'Erro desconhecido'));
            });
        }
    }

    function bulkExport() {
        const ids = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
        window.location.href = 'actions/export_actions.php?type=stock&subset=' + ids.join(',');
    }
    
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').innerHTML = '<img src="' + e.target.result + '" style="width:100%; height:100%; object-fit:cover;">';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
</body>
</html>