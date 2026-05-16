<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

include 'includes/db_connect.php';

$role = $_SESSION["role"] ?? 1; // 0 = Admin, 1 = User
$is_admin = ($role == 0);

// Contagem de Utilizadores
$res_users = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = ($res_users) ? $res_users->fetch_assoc()['total'] : 0;

// Quantidade Total de Produtos
$res_stock = $conn->query("SELECT SUM(quantidade) as total FROM produtos");
$total_products = ($res_stock && $res_stock->num_rows > 0) ? $res_stock->fetch_assoc()['total'] : 0;
if (!$total_products)
    $total_products = 0;

// Valor Total do Stock
$res_value = $conn->query("SELECT SUM(quantidade * preco_unit) as total FROM produtos");
$total_value = ($res_value && $res_value->num_rows > 0) ? $res_value->fetch_assoc()['total'] : 0;

// Produtos com Stock Crítico (<= 5)
$res_critical = $conn->query("SELECT COUNT(*) as total FROM produtos WHERE quantidade <= 5");
$critical_stock = ($res_critical) ? $res_critical->fetch_assoc()['total'] : 0;

// Entradas vs Saidas nos últimos 30 dias (Para o Gráfico)
$hoje = date('Y-m-d');
$trinta_dias_atras = date('Y-m-d', strtotime('-30 days'));

$entradas_sql = "SELECT DATE(data) as d, COUNT(*) as c FROM ent_cab WHERE data >= '$trinta_dias_atras' GROUP BY DATE(data)";
$res_in = $conn->query($entradas_sql);
$entradas_data = [];
if ($res_in) {
    while ($row = $res_in->fetch_assoc()) {
        $entradas_data[$row['d']] = $row['c'];
    }
}

$saidas_sql = "SELECT DATE(data) as d, COUNT(*) as c FROM sai_cab WHERE data >= '$trinta_dias_atras' GROUP BY DATE(data)";
$res_out = $conn->query($saidas_sql);
$saidas_data = [];
if ($res_out) {
    while ($row = $res_out->fetch_assoc()) {
        $saidas_data[$row['d']] = $row['c'];
    }
}

// Resumo Financeiro (últimos 30 dias) - usando estrutura correta
$resumo_sql = "SELECT 
                  SUM(CASE WHEN c.tipo = 'ENTRADA' THEN l.quantidade * l.preço ELSE 0 END) as total_compras,
                  SUM(CASE WHEN c.tipo = 'SAIDA' THEN l.quantidade * l.preço ELSE 0 END) as total_vendas,
                  SUM(CASE WHEN c.tipo = 'SAIDA' THEN l.quantidade * l.preço ELSE 0 END) - 
                  SUM(CASE WHEN c.tipo = 'ENTRADA' THEN l.quantidade * l.preço ELSE 0 END) as lucro
               FROM (
                   SELECT 'ENTRADA' as tipo, n_cab, data FROM ent_cab WHERE data >= '$trinta_dias_atras'
                   UNION ALL
                   SELECT 'SAIDA' as tipo, n_cab, data FROM sai_cab WHERE data >= '$trinta_dias_atras'
               ) c
               JOIN linhas l ON l.id = c.n_cab";
$res_resumo = $conn->query($resumo_sql);
$resumo_financeiro = ['compras' => 0, 'vendas' => 0, 'lucro' => 0];
if ($res_resumo) {
    $row = $res_resumo->fetch_assoc();
    $resumo_financeiro = [
        'compras' => $row['total_compras'] ?? 0,
        'vendas' => $row['total_vendas'] ?? 0,
        'lucro' => $row['lucro'] ?? 0
    ];
}

// Preparar arrays para Chart.js
$labels = [];
$data_in = [];
$data_out = [];

for ($i = 29; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($date_str));
    $data_in[] = isset($entradas_data[$date_str]) ? $entradas_data[$date_str] : 0;
    $data_out[] = isset($saidas_data[$date_str]) ? $saidas_data[$date_str] : 0;
}

$status = "Operacional";
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title> DASHBOARD</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(188, 111, 241, 0.1);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(188, 111, 241, 0.4);
        }

        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2.5rem;
            opacity: 0.1;
            color: #bc6ff1;
        }

        .stat-title {
            color: #aaa;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }

        .stat-subtitle {
            font-size: 0.8rem;
            color: #888;
        }

        .danger-text {
            color: #ff4b2b !important;
        }

        .success-text {
            color: #00ffcc !important;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(188, 111, 241, 0.1);
            margin-bottom: 30px;
            height: 350px;
        }

        .role-badge {
            background:
                <?php echo $is_admin ? '#bc6ff1' : '#4b5563'; ?>
            ;
            color: #fff;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 10px;
            vertical-align: middle;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                text-shadow: 0 0 5px #00ffcc;
            }

            50% {
                opacity: 0.3;
                text-shadow: none;
            }

            100% {
                opacity: 1;
                text-shadow: 0 0 5px #00ffcc;
            }
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notifications-panel {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(188, 111, 241, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
        }

        .notif-item {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notif-item:last-child {
            border: none;
        }

        .notif-msg {
            font-size: 0.9rem;
            color: #ccc;
        }

        .notif-date {
            font-size: 0.7rem;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="background-overlay"></div>

    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>

        <main class="content-area">
            <header class="main-header" style="margin-bottom: 25px;">
                <h2>Painel de Controlo</h2>
                <p>Métricas em tempo real do sistema de gestão <strong>TSTORE</strong></p>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-boxes-stacked stat-icon"></i>
                    <div class="stat-title">Unidades em Armazém</div>
                    <div class="stat-value"><?php echo number_format($total_products, 0, ',', '.'); ?></div>
                    <div class="stat-subtitle">Soma das quantidades em stock</div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-euro-sign stat-icon" style="color: #00ffcc;"></i>
                    <div class="stat-title">Valor do Stock</div>
                    <div class="stat-value success-text"><?php echo number_format($total_value, 2, ',', '.'); ?>
                        <?php echo $currency; ?></div>
                    <div class="stat-subtitle">Calculado ao preço de venda atual</div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-triangle-exclamation stat-icon" style="color: #ff4b2b;"></i>
                    <div class="stat-title">Avisos de Stock</div>
                    <div class="stat-value <?php echo $critical_stock > 0 ? 'danger-text' : ''; ?>">
                        <?php echo $critical_stock; ?>
                    </div>
                    <div class="stat-subtitle">Produtos com quantidade ≤ <?php echo $stock_limit; ?></div>
                </div>

                <?php if ($is_admin): ?>
                    <div class="stat-card">
                        <i class="fas fa-users stat-icon"></i>
                        <div class="stat-title">Utilizadores Ativos</div>
                        <div class="stat-value"><?php echo $total_users; ?></div>
                        <div class="stat-subtitle">Contas registadas na plataforma</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Resumo Financeiro -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <i class="fas fa-arrow-trend-up stat-icon" style="color: #10b981;"></i>
                    <div class="stat-title">Vendas (30 dias)</div>
                    <div class="stat-value success-text"><?php echo number_format($resumo_financeiro['vendas'], 2, ',', '.'); ?> <?php echo $currency; ?></div>
                    <div class="stat-subtitle">Total de vendas no período</div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-arrow-trend-down stat-icon" style="color: #f59e0b;"></i>
                    <div class="stat-title">Compras (30 dias)</div>
                    <div class="stat-value"><?php echo number_format($resumo_financeiro['compras'], 2, ',', '.'); ?> <?php echo $currency; ?></div>
                    <div class="stat-subtitle">Total de compras no período</div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-chart-line stat-icon" style="color: #8b5cf6;"></i>
                    <div class="stat-title">Lucro (30 dias)</div>
                    <div class="stat-value <?php echo $resumo_financeiro['lucro'] >= 0 ? 'success-text' : 'danger-text'; ?>">
                        <?php echo number_format($resumo_financeiro['lucro'], 2, ',', '.'); ?> <?php echo $currency; ?>
                    </div>
                    <div class="stat-subtitle">Margem de lucro no período</div>
                </div>
            </div>

            <div class="chart-container">
                <h3 style="color: #bc6ff1; margin-bottom: 15px; font-size: 1.1rem;">Fluxo de Movimentos (Últimos 30
                    Dias)</h3>
                <canvas id="movimentosChart"></canvas>
            </div>

        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('movimentosChart').getContext('2d');

            const dataIn = <?php echo json_encode($data_in); ?>;
            const dataOut = <?php echo json_encode($data_out); ?>;
            const labels = <?php echo json_encode($labels); ?>;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Entradas de Stock',
                            data: dataIn,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Saídas de Stock',
                            data: dataOut,
                            borderColor: '#ff4b2b',
                            backgroundColor: 'rgba(255, 75, 43, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#aaa', font: { family: "'Plus Jakarta Sans', sans-serif" } }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#888', stepSize: 1 },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        },
                        x: {
                            ticks: { color: '#888' },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        }
                    }
                }
            });
        });
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
        window.addEventListener('click', function (e) {
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
</body>

</html>
