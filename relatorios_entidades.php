<?php
/**
 * @file relatorios_entidades.php
 * @brief Relatórios por Entidade (Clientes/Fornecedores) - TSTORE
 * @author Antigravity
 * @date 2026-04-28
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION["role"] ?? 1;
$is_admin = ($role == 0);

// Processar filtros
$tipo_entidade = $_GET['tipo'] ?? 'cliente';
$entidade_id = $_GET['entidade'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-90 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// Buscar entidades
$clientes = $conn->query("SELECT id_cliente, nome FROM clientes ORDER BY nome ASC");
$fornecedores = $conn->query("SELECT id_fornecedor, nome FROM fornecedores ORDER BY nome ASC");

// Dados da entidade selecionada
$entidade_info = null;
$movimentos = [];
$estatisticas = [];

if ($entidade_id) {
    if ($tipo_entidade == 'cliente') {
        // Buscar informações do cliente
        $stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
        $stmt->bind_param("i", $entidade_id);
        $stmt->execute();
        $entidade_info = $stmt->get_result()->fetch_assoc();
        
        // Buscar movimentos de saídas (vendas)
        $stmt = $conn->prepare("
            SELECT s.n_cab, s.data, 'SAÍDA' as tipo, l.id_produto, p.descricao, l.quantidade, l.preço,
                   (l.quantidade * l.preço) as total
            FROM sai_cab s
            LEFT JOIN linhas l ON s.n_cab = l.id
            LEFT JOIN produtos p ON l.id_produto = p.id_produto
            WHERE s.id_cliente = ? AND s.data BETWEEN ? AND ?
            ORDER BY s.data DESC
        ");
        $stmt->bind_param("iss", $entidade_id, $data_inicio, $data_fim);
        $stmt->execute();
        $movimentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calcular estatísticas
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT s.n_cab) as total_documentos,
                COUNT(l.id) as total_linhas,
                SUM(l.quantidade) as total_quantidade,
                SUM(l.quantidade * l.preço) as total_valor,
                AVG(l.quantidade * l.preço) as media_valor,
                MIN(s.data) as primeira_compra,
                MAX(s.data) as ultima_compra
            FROM sai_cab s
            LEFT JOIN linhas l ON s.n_cab = l.id
            WHERE s.id_cliente = ? AND s.data BETWEEN ? AND ?
        ");
        $stmt->bind_param("iss", $entidade_id, $data_inicio, $data_fim);
        $stmt->execute();
        $estatisticas = $stmt->get_result()->fetch_assoc();
        
    } else {
        // Buscar informações do fornecedor
        $stmt = $conn->prepare("SELECT * FROM fornecedores WHERE id_fornecedor = ?");
        $stmt->bind_param("i", $entidade_id);
        $stmt->execute();
        $entidade_info = $stmt->get_result()->fetch_assoc();
        
        // Buscar movimentos de entradas (compras)
        $stmt = $conn->prepare("
            SELECT e.n_cab, e.data, 'ENTRADA' as tipo, l.id_produto, p.descricao, l.quantidade, l.preço,
                   (l.quantidade * l.preço) as total
            FROM ent_cab e
            LEFT JOIN linhas l ON e.n_cab = l.id
            LEFT JOIN produtos p ON l.id_produto = p.id_produto
            WHERE e.id_fornecedor = ? AND e.data BETWEEN ? AND ?
            ORDER BY e.data DESC
        ");
        $stmt->bind_param("iss", $entidade_id, $data_inicio, $data_fim);
        $stmt->execute();
        $movimentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calcular estatísticas
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT e.n_cab) as total_documentos,
                COUNT(l.id) as total_linhas,
                SUM(l.quantidade) as total_quantidade,
                SUM(l.quantidade * l.preço) as total_valor,
                AVG(l.quantidade * l.preço) as media_valor,
                MIN(e.data) as primeira_compra,
                MAX(e.data) as ultima_compra
            FROM ent_cab e
            LEFT JOIN linhas l ON e.n_cab = l.id
            WHERE e.id_fornecedor = ? AND e.data BETWEEN ? AND ?
        ");
        $stmt->bind_param("iss", $entidade_id, $data_inicio, $data_fim);
        $stmt->execute();
        $estatisticas = $stmt->get_result()->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatórios por Entidade - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, rgba(188, 111, 241, 0.15), rgba(142, 68, 173, 0.08));
            border: 1px solid rgba(188, 111, 241, 0.4);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #bc6ff1;
            margin: 10px 0;
        }
        .stat-label {
            color: #aaa;
            font-size: 0.9rem;
        }
        .entity-info {
            background: linear-gradient(135deg, rgba(188, 111, 241, 0.15), rgba(142, 68, 173, 0.08));
            border: 1px solid rgba(188, 111, 241, 0.4);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .entity-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        .entity-icon {
            font-size: 3rem;
            color: #bc6ff1;
        }
        .entity-details {
            flex: 1;
        }
        .entity-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }
        .entity-meta {
            color: #aaa;
            font-size: 0.9rem;
        }
        .movimentos-table {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            overflow: hidden;
        }
        .movimentos-table th {
            background: rgba(188, 111, 241, 0.2);
            color: #fff;
            padding: 15px;
            text-align: left;
        }
        .movimentos-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .movimento-tipo {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .tipo-entrada {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .tipo-saida {
            background: rgba(255, 75, 43, 0.2);
            color: #ff4b2b;
        }
        .total-valor {
            font-weight: bold;
            color: #bc6ff1;
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
                    <button class="nav-btn" onclick="location.href='relatorios.php'" title="Voltar">
                        <i class="fas fa-arrow-left" style="margin:0;"></i>
                    </button>
                    <div>
                        <h2>Relatórios por Entidade</h2>
                        <p>Histórico de compras/vendas por cliente/fornecedor</p>
                    </div>
                </div>
                <?php if ($entidade_info): ?>
                <button class="nav-btn active" onclick="exportarPDF()">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
                <?php endif; ?>
            </header>

            <!-- Filtros -->
            <div class="entity-info">
                <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <label>Tipo de Entidade</label>
                        <select name="tipo" onchange="this.form.submit()">
                            <option value="cliente" <?php echo $tipo_entidade == 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                            <option value="fornecedor" <?php echo $tipo_entidade == 'fornecedor' ? 'selected' : ''; ?>>Fornecedor</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Entidade</label>
                        <select name="entidade" onchange="this.form.submit()">
                            <option value="">-- Selecione --</option>
                            <?php
                            if ($tipo_entidade == 'cliente' && $clientes) {
                                while ($c = $clientes->fetch_assoc()) {
                                    echo "<option value='" . $c['id_cliente'] . "' " . ($entidade_id == $c['id_cliente'] ? 'selected' : '') . ">" . htmlspecialchars($c['nome']) . "</option>";
                                }
                            } elseif ($tipo_entidade == 'fornecedor' && $fornecedores) {
                                while ($f = $fornecedores->fetch_assoc()) {
                                    echo "<option value='" . $f['id_fornecedor'] . "' " . ($entidade_id == $f['id_fornecedor'] ? 'selected' : '') . ">" . htmlspecialchars($f['nome']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Data Início</label>
                        <input type="date" name="data_inicio" value="<?php echo $data_inicio; ?>" onchange="this.form.submit()">
                    </div>
                    
                    <div>
                        <label>Data Fim</label>
                        <input type="date" name="data_fim" value="<?php echo $data_fim; ?>" onchange="this.form.submit()">
                    </div>
                </form>
            </div>

            <?php if ($entidade_info): ?>
                <!-- Informações da Entidade -->
                <div class="entity-info">
                    <div class="entity-header">
                        <div class="entity-icon">
                            <i class="fas fa-<?php echo $tipo_entidade == 'cliente' ? 'users' : 'truck'; ?>"></i>
                        </div>
                        <div class="entity-details">
                            <div class="entity-name"><?php echo htmlspecialchars($entidade_info['nome']); ?></div>
                            <div class="entity-meta">
                                <?php if ($entidade_info['nif']) echo "NIF: " . htmlspecialchars($entidade_info['nif']) . " | "; ?>
                                <?php if ($entidade_info['email']) echo "Email: " . htmlspecialchars($entidade_info['email']) . " | "; ?>
                                <?php if ($entidade_info['telefone']) echo "Tel: " . htmlspecialchars($entidade_info['telefone']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($estatisticas['total_documentos'] ?? 0, 0, ',', '.'); ?></div>
                        <div class="stat-label">Total de Documentos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($estatisticas['total_linhas'] ?? 0, 0, ',', '.'); ?></div>
                        <div class="stat-label">Total de Linhas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($estatisticas['total_quantidade'] ?? 0, 0, ',', '.'); ?></div>
                        <div class="stat-label">Total de Unidades</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($estatisticas['total_valor'] ?? 0, 2, ',', '.'); ?>€</div>
                        <div class="stat-label">Valor Total</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($estatisticas['media_valor'] ?? 0, 2, ',', '.'); ?>€</div>
                        <div class="stat-label">Valor Médio</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label" style="margin-bottom: 10px;">Período</div>
                        <div style="font-size: 0.8rem; color: #aaa;">
                            <?php echo date('d/m/Y', strtotime($estatisticas['primeira_compra'] ?? $data_inicio)); ?> - 
                            <?php echo date('d/m/Y', strtotime($estatisticas['ultima_compra'] ?? $data_fim)); ?>
                        </div>
                    </div>
                </div>

                <!-- Movimentos -->
                <div class="table-container">
                    <table class="movimentos-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Documento</th>
                                <th>Tipo</th>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th>Preço Unit.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimentos)): ?>
                                <?php foreach ($movimentos as $mov): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($mov['data'])); ?></td>
                                    <td>#<?php echo $mov['n_cab']; ?></td>
                                    <td>
                                        <span class="movimento-tipo tipo-<?php echo strtolower($mov['tipo']); ?>">
                                            <?php echo $mov['tipo']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($mov['descricao']); ?></td>
                                    <td><?php echo number_format($mov['quantidade'], 0, ',', '.'); ?></td>
                                    <td><?php echo number_format($mov['preço'], 2, ',', '.'); ?>€</td>
                                    <td class="total-valor"><?php echo number_format($mov['total'], 2, ',', '.'); ?>€</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 50px; opacity: 0.5;">
                                        <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                        <p>Sem movimentos encontrados no período selecionado.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function exportarPDF() {
            const url = 'exportar_pdf_entidade.php?tipo=<?php echo $tipo_entidade; ?>&entidade=<?php echo $entidade_id; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>';
            window.open(url, '_blank');
        }
    </script>
</body>
</html>
