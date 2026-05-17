<?php
/**
 * @file relatorios.php
 * @brief Portal de Relatórios Avançados - TSTORE.
 * @author Antigravity
 * @date 2026-03-17
 */

include 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteção de acesso: apenas administradores
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

$is_admin = true;

// Processar filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-15 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$categoria_filtro = $_GET['categoria'] ?? '';

// Array auxiliar apenas para saber se existem filtros aplicados
$filtros = [];
if (isset($_GET['data_inicio']) || isset($_GET['categoria'])) {
    $filtros = true;
}

// 1. Distribuição Financeira por Categoria (Pie Chart) - Stock Atual
$sql_cat = "SELECT c.descricao as cat, SUM(p.quantidade * p.preco_unit) as total_valor
            FROM produtos p
            JOIN categoria c ON p.id_categoria = c.id_categoria";

if ($categoria_filtro) {
    $sql_cat .= " WHERE p.id_categoria = '" . $conn->real_escape_string($categoria_filtro) . "'";
}
$sql_cat .= " GROUP BY p.id_categoria";

$res_cat = $conn->query($sql_cat);
$cat_labels = [];
$cat_values = [];
while ($row = $res_cat->fetch_assoc()) {
    $cat_labels[] = $row['cat'];
    $cat_values[] = (float)$row['total_valor'];
}

// 2. Top 5 Produtos Mais Movimentados (Bar Chart) - Baseado em TODOS os movimentos no período
$sql_top = "SELECT p.descricao, SUM(l.quantidade) as total_qty 
            FROM linhas l 
            JOIN produtos p ON l.id_produto = p.id_produto 
            WHERE (
                l.id IN (SELECT n_cab FROM sai_cab WHERE DATE(data) >= '$data_inicio' AND DATE(data) <= '$data_fim')
                OR 
                l.id IN (SELECT n_cab FROM ent_cab WHERE DATE(data) >= '$data_inicio' AND DATE(data) <= '$data_fim')
            )";
if ($categoria_filtro) {
    $sql_top .= " AND p.id_categoria = '" . $conn->real_escape_string($categoria_filtro) . "'";
}
$sql_top .= " GROUP BY l.id_produto ORDER BY total_qty DESC LIMIT 5";

$res_top = $conn->query($sql_top);
$top_labels = [];
$top_values = [];
if ($res_top) {
    while ($row = $res_top->fetch_assoc()) {
        $top_labels[] = $row['descricao'];
        $top_values[] = (int)$row['total_qty'];
    }
}

// 3. Fluxo Financeiro Diário (No intervalo selecionado)
// Entradas
$sql_flux_in = "SELECT DATE(h.data) as d, SUM(l.quantidade * l.preço) as total 
                FROM ent_cab h 
                JOIN linhas l ON h.n_cab = l.id 
                WHERE DATE(h.data) >= '$data_inicio' AND DATE(h.data) <= '$data_fim'";
if ($categoria_filtro) {
    $sql_flux_in .= " AND l.id_categoria = '" . $conn->real_escape_string($categoria_filtro) . "'";
}
$sql_flux_in .= " GROUP BY DATE(h.data)";
$res_flux_in = $conn->query($sql_flux_in);
$flux_in_data = [];
if ($res_flux_in) {
    while ($row = $res_flux_in->fetch_assoc()) { 
        $flux_in_data[$row['d']] = $row['total']; 
    }
}

// Saídas
$sql_flux_out = "SELECT DATE(h.data) as d, SUM(l.quantidade * l.preço) as total 
                 FROM sai_cab h 
                 JOIN linhas l ON h.n_cab = l.id 
                 WHERE DATE(h.data) >= '$data_inicio' AND DATE(h.data) <= '$data_fim'";
if ($categoria_filtro) {
    $sql_flux_out .= " AND l.id_categoria = '" . $conn->real_escape_string($categoria_filtro) . "'";
}
$sql_flux_out .= " GROUP BY DATE(h.data)";
$res_flux_out = $conn->query($sql_flux_out);
$flux_out_data = [];
if ($res_flux_out) {
    while ($row = $res_flux_out->fetch_assoc()) { 
        $flux_out_data[$row['d']] = $row['total']; 
    }
}

// Construir os Labels e Valores Dinamicamente para o intervalo
$flux_labels = [];
$flux_in_final = [];
$flux_out_final = [];

$current_time = strtotime($data_inicio);
$end_time = strtotime($data_fim);

while ($current_time <= $end_time) {
    $d = date('Y-m-d', $current_time);
    $flux_labels[] = date('d/m', $current_time);
    $flux_in_final[] = $flux_in_data[$d] ?? 0;
    $flux_out_final[] = $flux_out_data[$d] ?? 0;
    
    // Avançar 1 dia
    $current_time = strtotime('+1 day', $current_time);
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatórios Avançados - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 30px;
        }
        .reports-grid .report-card {
            border-radius: 20px;
            padding: 25px;
            height: 400px;
            display: flex;
            flex-direction: column;
        }
        .full-row { grid-column: span 2; }
        .report-card h3 {
            color: #bc6ff1;
            font-size: 1.1rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .chart-wrap { flex: 1; position: relative; min-height: 0; }

        /* PRINT STYLES */
        @media print {
            body { background: white !important; color: black !important; }
            .background-overlay, .navbar, .nav-btn, .close-modal, .logout-btn { display: none !important; }
            .wrapper { padding: 0 !important; height: auto !important; }
            .content-area { background: none !important; backdrop-filter: none !important; border: none !important; padding: 0 !important; overflow: visible !important; }
            .report-card { border: 1px solid #ddd !important; background: white !important; page-break-inside: avoid; }
            .report-card h3 { color: black !important; }
            .reports-grid { display: block !important; }
            .report-card { margin-bottom: 20px; height: 350px !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 30px; border-bottom: 2px solid #bc6ff1; padding-bottom: 15px; }
            .t-letter { color: black !important; }
        }
        .nav-right { display: flex; align-items: center; gap: 15px; }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>

        <div class="print-header">
            <h1 style="color: #bc6ff1; font-weight: 900; margin-bottom: 5px;">TSTORE</h1>
            <h2 style="font-size: 1.2rem; color: #333;">Relatório de Desempenho Administrativo</h2>
            <p style="font-size: 0.8rem; color: #666;">Gerado por <?php echo htmlspecialchars($_SESSION['user']); ?> em <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <main class="content-area">
            <header class="table-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-btn" onclick="location.href='gestao.php'" title="Voltar"><i class="fas fa-arrow-left" style="margin:0;"></i></button>
                    <div>
                        <h2>Relatórios Avançados</h2>
                        <p>Análise detalhada de performance e stock</p>
                    </div>
                </div>
                <div style="display: flex; gap: 15px;">
                    <button class="nav-btn" onclick="toggleFilters()">
                        <i class="fas fa-filter"></i> Filtros
                    </button>
                    <div style="position: relative;">
                        <button class="nav-btn" onclick="toggleExportMenu()">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <div id="exportMenu" style="display: none; position: absolute; right: 0; top: 100%; background: rgba(30, 15, 50, 0.95); border: 1px solid rgba(188, 111, 241, 0.3); border-radius: 10px; padding: 10px; min-width: 200px; z-index: 1000; margin-top: 10px;">
                            <button class="nav-btn" onclick="exportar('vendas')" style="width: 100%; text-align: left; margin-bottom: 5px;">
                                <i class="fas fa-shopping-cart"></i> Vendas Detalhadas
                            </button>
                            <button class="nav-btn" onclick="exportar('financeiro')" style="width: 100%; text-align: left; margin-bottom: 5px;">
                                <i class="fas fa-chart-line"></i> Relatório Financeiro
                            </button>
                            <button class="nav-btn" onclick="exportar('stock_critico')" style="width: 100%; text-align: left; margin-bottom: 5px;">
                                <i class="fas fa-exclamation-triangle"></i> Stock Crítico
                            </button>
                            <button class="nav-btn" onclick="exportar('stock')" style="width: 100%; text-align: left;">
                                <i class="fas fa-boxes"></i> Stock Completo
                            </button>
                        </div>
                    </div>
                    <div style="position: relative;">
                        <button class="nav-btn active" onclick="toggleFaturaMenu()">
                            <i class="fas fa-file-pdf"></i> Fatura Mensal
                        </button>
                        <div id="faturaMenu" style="display: none; position: absolute; right: 0; top: 100%; background: rgba(30, 15, 50, 0.95); border: 1px solid rgba(188, 111, 241, 0.3); border-radius: 10px; padding: 10px; min-width: 220px; z-index: 1000; margin-top: 10px;">
                            <div style="padding: 10px; border-bottom: 1px solid rgba(188, 111, 241, 0.2); margin-bottom: 10px;">
                                <label style="color: #aaa; font-size: 0.8rem; display: block; margin-bottom: 5px;">Mês</label>
                                <select id="faturaMes" class="full-width">
                                    <?php
                                    for ($i = 1; $i <= 12; $i++) {
                                        $mes_nome = date('F', mktime(0, 0, 0, $i, 1));
                                        $mes_pt = ['January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março', 'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho', 'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro', 'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'][$mes_nome];
                                        $selected = ($i == date('m')) ? 'selected' : '';
                                        echo "<option value='$i' $selected>$mes_pt</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div style="padding: 10px; border-bottom: 1px solid rgba(188, 111, 241, 0.2); margin-bottom: 10px;">
                                <label style="color: #aaa; font-size: 0.8rem; display: block; margin-bottom: 5px;">Ano</label>
                                <select id="faturaAno" class="full-width">
                                    <?php
                                    $ano_atual = date('Y');
                                    for ($i = $ano_atual; $i >= $ano_atual - 2; $i--) {
                                        $selected = ($i == $ano_atual) ? 'selected' : '';
                                        echo "<option value='$i' $selected>$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div style="padding: 10px; border-bottom: 1px solid rgba(188, 111, 241, 0.2); margin-bottom: 10px;">
                                <label style="color: #aaa; font-size: 0.8rem; display: block; margin-bottom: 5px;">Tipo</label>
                                <select id="faturaTipo" class="full-width">
                                    <option value="saida">Saídas (Vendas)</option>
                                    <option value="entrada">Entradas (Compras)</option>
                                    <option value="ambos">Completo (Ambos)</option>
                                </select>
                            </div>
                            <button class="nav-btn active" onclick="gerarFaturaMensal()" style="width: 100%;">
                                <i class="fas fa-file-pdf"></i> Gerar Fatura
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Formulário de Filtros -->
            <form id="filtersForm" method="GET" style="display: none; background: rgba(188, 111, 241, 0.05); border: 1px solid rgba(188, 111, 241, 0.2); border-radius: 15px; padding: 25px; margin-bottom: 30px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #aaa; font-size: 0.9rem;">Data Início</label>
                        <input type="date" name="data_inicio" value="<?php echo $data_inicio; ?>" class="full-width">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #aaa; font-size: 0.9rem;">Data Fim</label>
                        <input type="date" name="data_fim" value="<?php echo $data_fim; ?>" class="full-width">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #aaa; font-size: 0.9rem;">Categoria</label>
                        <select name="categoria" class="full-width">
                            <option value="">Todas as categorias</option>
                            <?php
                            $cats = $conn->query("SELECT * FROM categoria");
                            while ($c = $cats->fetch_assoc()) {
                                $selected = ($categoria_filtro == $c['id_categoria']) ? 'selected' : '';
                                echo "<option value='{$c['id_categoria']}' $selected>{$c['descricao']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="submit" class="nav-btn active">
                        <i class="fas fa-search"></i> Aplicar Filtros
                    </button>
                    <button type="button" onclick="clearFilters()" class="nav-btn">
                        <i class="fas fa-times"></i> Limpar
                    </button>
                </div>
            </form>

            <div class="reports-grid">
                <!-- Card Relatórios por Entidade -->
                <div class="report-card" style="background: linear-gradient(135deg, rgba(188, 111, 241, 0.18), rgba(142, 68, 173, 0.1)) !important; border: 1px solid rgba(188, 111, 241, 0.4) !important;">
                    <h3><i class="fas fa-users"></i> Relatórios por Entidade</h3>
                    <p style="color: #aaa; margin: 15px 0;">Histórico detalhado de compras/vendas por cliente ou fornecedor</p>
                    <div style="margin-top: auto;">
                        <button class="nav-btn" onclick="location.href='relatorios_entidades.php'" style="width: 100%; text-align: center;">
                            <i class="fas fa-search"></i> Ver Relatórios
                        </button>
                    </div>
                </div>

                <!-- Gráfico de Fluxo Financeiro -->
                <div class="report-card" style="background: linear-gradient(135deg, rgba(188, 111, 241, 0.18), rgba(142, 68, 173, 0.1)) !important; border: 1px solid rgba(188, 111, 241, 0.4) !important;">
                    <h3><i class="fas fa-wave-square"></i> Fluxo Financeiro (<?php echo $currency; ?>) - Período Selecionado</h3>
                    <div class="chart-wrap"><canvas id="fluxChart"></canvas></div>
                </div>

                <!-- Gráfico de Categorias -->
                <div class="report-card" style="background: linear-gradient(135deg, rgba(188, 111, 241, 0.18), rgba(142, 68, 173, 0.1)) !important; border: 1px solid rgba(188, 111, 241, 0.4) !important;">
                    <h3><i class="fas fa-chart-pie"></i> Valor em Stock por Categoria</h3>
                    <div class="chart-wrap"><canvas id="catChart"></canvas></div>
                </div>

                <!-- Gráfico de Top Produtos -->
                <div class="report-card" style="background: linear-gradient(135deg, rgba(188, 111, 241, 0.18), rgba(142, 68, 173, 0.1)) !important; border: 1px solid rgba(188, 111, 241, 0.4) !important;">
                    <h3><i class="fas fa-trophy"></i> Top 5 Produtos Movimentados (Qtd)</h3>
                    <div class="chart-wrap"><canvas id="topChart"></canvas></div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Funções para controlo dos filtros
    function toggleFilters() {
        const form = document.getElementById('filtersForm');
        const icon = document.getElementById('filterToggleIcon');
        const button = event.target.closest('button');

        if (form.style.display === 'none') {
            form.style.display = 'block';
            if (icon) icon.className = 'fas fa-chevron-up';
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
            if (icon) icon.className = 'fas fa-chevron-down';
            button.innerHTML = '<i class="fas fa-chevron-down" id="filterToggleIcon"></i> Expandir';
        }
    }

    function clearFilters() {
        window.location.href = 'relatorios.php';
    }

    function toggleExportMenu() {
        const menu = document.getElementById('exportMenu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }

    function toggleFaturaMenu() {
        const menu = document.getElementById('faturaMenu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }

    function gerarFaturaMensal() {
        const mes = document.getElementById('faturaMes').value;
        const ano = document.getElementById('faturaAno').value;
        const tipo = document.getElementById('faturaTipo').value;

        const url = `actions/gerar_fatura_mensal.php?mes=${mes}&ano=${ano}&tipo=${tipo}`;
        window.open(url, '_blank');
        toggleFaturaMenu();
    }

    function gerarFaturaMensalAtual() {
        const mesAtual = <?php echo date('m'); ?>;
        const anoAtual = <?php echo date('Y'); ?>;
        const url = `actions/gerar_fatura_mensal.php?mes=${mesAtual}&ano=${anoAtual}&tipo=ambos`;
        window.open(url, '_blank');
    }

    function exportar(tipo) {
        const dataInicio = document.querySelector('input[name="data_inicio"]')?.value || '';
        const dataFim = document.querySelector('input[name="data_fim"]')?.value || '';
        const cliente = document.querySelector('input[name="cliente"]')?.value || '';

        let url = `actions/export_actions.php?type=${tipo}`;
        if (dataInicio) url += `&data_inicio=${dataInicio}`;
        if (dataFim) url += `&data_fim=${dataFim}`;
        if (cliente) url += `&cliente=${encodeURIComponent(cliente)}`;

        window.open(url, '_blank');
        toggleExportMenu();
    }

    // Fechar menu de exportação ao clicar fora
    window.addEventListener('click', function(e) {
        if (!e.target.closest('button[onclick="toggleExportMenu()"]') && !e.target.closest('#exportMenu')) {
            const menu = document.getElementById('exportMenu');
            if (menu) menu.style.display = 'none';
        }
        if (!e.target.closest('button[onclick="toggleFaturaMenu()"]') && !e.target.closest('#faturaMenu')) {
            const menu = document.getElementById('faturaMenu');
            if (menu) menu.style.display = 'none';
        }
    });



    document.addEventListener('DOMContentLoaded', function() {
        // Opções partilhadas
        const fontConfig = { family: "'Poppins', sans-serif", color: '#aaa' };

        // 1. Flux Chart (Line)
        new Chart(document.getElementById('fluxChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($flux_labels); ?>,
                datasets: [
                    { label: 'Entradas (€)', data: <?php echo json_encode($flux_in_final); ?>, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, tension: 0.4 },
                    { label: 'Saídas (€)', data: <?php echo json_encode($flux_out_final); ?>, borderColor: '#ff4b2b', backgroundColor: 'rgba(255, 75, 43, 0.1)', fill: true, tension: 0.4 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888' } }, x: { grid: { display: false }, ticks: { color: '#888' } } }, plugins: { legend: { labels: { color: '#aaa' } } } }
        });

        // 2. Category Pie Chart
        new Chart(document.getElementById('catChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($cat_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($cat_values); ?>,
                    backgroundColor: ['#bc6ff1', '#892cdc', '#52057b', '#c06014', '#10b981', '#ff4b2b'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: '#aaa', padding: 20 } }, tooltip: { callbacks: { label: function(context) { return ' ' + Number(context.raw).toFixed(2).replace('.', ',') + ' €'; } } } }, cutout: '70%' }
        });

        // 3. Top Products Bar Chart
        new Chart(document.getElementById('topChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($top_labels); ?>,
                datasets: [{
                    label: 'Unidades Movimentadas',
                    data: <?php echo json_encode($top_values); ?>,
                    backgroundColor: '#bc6ff1',
                    borderRadius: 8
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#888' } }, y: { grid: { display: false }, ticks: { color: '#888' } } } }
        });
    });
    </script>
</script>
</body>
</html>
