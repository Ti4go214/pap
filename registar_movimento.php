<?php
include 'includes/db_connect.php';
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

if ($_SESSION['role'] != 0 && $_SESSION['role'] != 2) {
    header("Location: movimentos.php");
    exit();
}

// Obter produtos para dropdown
$prods = $conn->query("SELECT * FROM produtos ORDER BY descricao");

// Obter clientes e fornecedores
$clientes = $conn->query("SELECT * FROM clientes ORDER BY nome ASC");
$fornecedores = $conn->query("SELECT * FROM fornecedores ORDER BY nome ASC");
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Registar Movimento - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }

        .split-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        .form-panel,
        .lines-panel {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(188, 111, 241, 0.1);
        }

        .section-title {
            color: #bc6ff1;
            margin-bottom: 20px;
            font-size: 1.2rem;
            border-bottom: 1px solid rgba(188, 111, 241, 0.2);
            padding-bottom: 10px;
        }

        .add-line-btn {
            background: #bc6ff1;
            color: #fff;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
            width: 100%;
        }

        .add-line-btn:hover {
            background: #a04ee0;
        }

        .lines-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .lines-table th {
            text-align: left;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #ccc;
        }

        .lines-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        input,
        select {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 5px;
            color: #fff;
            margin-bottom: 10px;
        }

        input:focus,
        select:focus {
            border-color: #bc6ff1;
            outline: none;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.8rem;
            color: #aaa;
        }
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
                <button class="nav-btn active" onclick="location.href='movimentos.php'">
                    <i class="fas fa-exchange-alt"></i> Movimentos
                </button>

                <?php if (($_SESSION['role'] ?? 1) == 0): ?>
                    <button class="nav-btn" onclick="location.href='gestao.php'">
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
            <form action="actions/movimento_actions.php" method="POST" id="movimentoForm">
                <div class="split-container">

                    <!-- Cabeçalho -->
                    <div class="form-panel">
                        <h3 class="section-title">Dados do Documento</h3>

                        <label>Tipo de Movimento</label>
                        <select name="tipo" id="tipo_movimento" onchange="atualizarEntidade()"
                            style="background: #1e0f32; border-color: #bc6ff1; color: #fff; font-weight: bold;">
                            <option value="ENTRADA">ENTRADA (Compra/Stock In)</option>
                            <option value="SAIDA">SAÍDA (Venda/Stock Out)</option>
                        </select>

                        <label id="label_entidade">Fornecedor</label>
                        <select name="entidade" id="entidade_select" required
                            style="background: #1e0f32; border-color: #bc6ff1; color: #fff;">
                            <option value="">-- Selecione um fornecedor --</option>
                            <?php
                            if ($fornecedores && $fornecedores->num_rows > 0) {
                                while ($f = $fornecedores->fetch_assoc()) {
                                    echo "<option value='" . $f['id_fornecedor'] . "'>" . htmlspecialchars($f['nome']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        
                        <!-- Dropdown de clientes (inicialmente oculto) -->
                        <select name="entidade_cliente" id="cliente_select" required
                            style="background: #1e0f32; border-color: #bc6ff1; color: #fff; display: none;">
                            <option value="">-- Selecione um cliente --</option>
                            <?php
                            if ($clientes && $clientes->num_rows > 0) {
                                while ($c = $clientes->fetch_assoc()) {
                                    echo "<option value='" . $c['id_cliente'] . "'>" . htmlspecialchars($c['nome']) . "</option>";
                                }
                            }
                            ?>
                        </select>

                        <label>Data</label>
                        <input type="datetime-local" name="data" value="<?php echo date('Y-m-d\TH:i'); ?>" required>

                        <button type="submit" name="btn_save_mov" class="nav-btn active"
                            style="width: 100%; margin-top: 20px; text-align: center; justify-content: center;">
                            <i class="fas fa-save"></i> Finalizar Movimento
                        </button>
                    </div>

                    <!-- Linhas -->
                    <div class="lines-panel">
                        <h3 class="section-title">Produtos</h3>

                        <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 10px;">
                            <label>Selecionar Produto</label>
                            <select id="prod_select" onchange="updatePrice()">
                                <option value="" data-price="0">-- Escolha um produto --</option>
                                <?php
                                if ($prods) {
                                    while ($p = $prods->fetch_assoc()) {
                                        echo "<option value='" . $p['id_produto'] . "' data-desc='" . $p['descricao'] . "' data-price='" . $p['preco_unit'] . "'>" . $p['descricao'] . " (Stock: " . $p['quantidade'] . ")</option>";
                                    }
                                }
                                ?>
                            </select>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label>Qtd</label>
                                    <input type="number" id="prod_qtd" value="1" min="1">
                                </div>
                                <div>
                                    <label>Preço Unit.</label>
                                    <input type="number" id="prod_price" step="0.01">
                                </div>
                            </div>

                            <button type="button" class="add-line-btn" onclick="addLine()">
                                <i class="fas fa-plus"></i> Adicionar Linha
                            </button>
                        </div>

                        <table class="lines-table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th style="width: 80px;">Qtd</th>
                                    <th style="width: 100px;">Preço</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="lines_body">
                                <!-- Linhas via JS -->
                            </tbody>
                        </table>

                        <!-- Hidden inputs generated here -->
                        <div id="hidden_inputs"></div>
                    </div>

                </div>
            </form>
        </main>
    </div>

    <script>
        const currency = "<?php echo $currency ?? '€'; ?>";
        
        function atualizarEntidade() {
            const tipo = document.getElementById('tipo_movimento').value;
            const label = document.getElementById('label_entidade');
            const fornecedorSelect = document.getElementById('entidade_select');
            const clienteSelect = document.getElementById('cliente_select');
            
            if (tipo === 'ENTRADA') {
                label.textContent = 'Fornecedor';
                fornecedorSelect.style.display = 'block';
                fornecedorSelect.required = true;
                clienteSelect.style.display = 'none';
                clienteSelect.required = false;
                clienteSelect.name = 'entidade_cliente';
                fornecedorSelect.name = 'entidade';
            } else {
                label.textContent = 'Cliente';
                fornecedorSelect.style.display = 'none';
                fornecedorSelect.required = false;
                clienteSelect.style.display = 'block';
                clienteSelect.required = true;
                clienteSelect.name = 'entidade';
                fornecedorSelect.name = 'entidade_fornecedor';
            }
        }
        
        function updatePrice() {
            let select = document.getElementById('prod_select');
            let price = select.options[select.selectedIndex].getAttribute('data-price');
            document.getElementById('prod_price').value = price;
        }

        let lineCount = 0;

        function addLine() {
            let select = document.getElementById('prod_select');
            let id = select.value;
            if (!id) return alert("Selecione um produto");

            let desc = select.options[select.selectedIndex].getAttribute('data-desc');
            let qtd = document.getElementById('prod_qtd').value;
            let price = document.getElementById('prod_price').value;

            lineCount++;

            let row = `
                <tr id="row_${lineCount}">
                    <td>${desc}</td>
                    <td>${qtd}</td>
                    <td>${price} ${currency}</td>
                    <td><button type="button" onclick="removeLine(${lineCount})" style="color:red; background:none; border:none; cursor:pointer;"><i class="fas fa-trash"></i></button></td>
                </tr>
            `;

            document.getElementById('lines_body').insertAdjacentHTML('beforeend', row);

            // Adicionar inputs ocultos para PHP
            let inputs = `
                <div id="input_${lineCount}">
                    <input type="hidden" name="linhas[${lineCount}][id_produto]" value="${id}">
                    <input type="hidden" name="linhas[${lineCount}][descricao]" value="${desc}">
                    <input type="hidden" name="linhas[${lineCount}][quantidade]" value="${qtd}">
                    <input type="hidden" name="linhas[${lineCount}][preco]" value="${price}">
                </div>
            `;
            document.getElementById('hidden_inputs').insertAdjacentHTML('beforeend', inputs);

            // Reiniciar campos
            select.value = "";
            document.getElementById('prod_qtd').value = 1;
            document.getElementById('prod_price').value = "";
        }

        function removeLine(id) {
            document.getElementById('row_' + id).remove();
            document.getElementById('input_' + id).remove();
        }
    </script>
<script>

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