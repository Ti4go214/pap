<?php
/**
 * @file gerar_fatura_mensal.php
 * @brief Sistema de Geração de Fatura Mensal Profissional - TSTORE
 * @author Antigravity
 * @date 2026-04-28
 */

include '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteção de acesso
if (!isset($_SESSION['user'])) {
    die("Acesso negado");
}

// Obter parâmetros
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$tipo = $_GET['tipo'] ?? 'saida'; // 'entrada', 'saida' ou 'ambos'

// Validar parâmetros
if (!in_array($tipo, ['entrada', 'saida', 'ambos'])) {
    die("Tipo inválido");
}

// Configurações da empresa
$nome_empresa = $config['app_name'] ?? 'TSTORE';
$moeda = $config['currency'] ?? '€';

// Determinar período
$data_inicio = "$ano-$mes-01";
$data_fim = date('Y-m-t', strtotime($data_inicio));
$nome_mes = date('F', strtotime($data_inicio));
$nome_mes_pt = [
    'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
    'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
    'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
    'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
];
$nome_mes = $nome_mes_pt[$nome_mes] ?? $nome_mes;

// Construir query baseada no tipo
$where_tipo = "";
if ($tipo == 'entrada') {
    $where_tipo = "AND h.tipo = 'ENTRADA'";
    $titulo_doc = "RELATÓRIO MENSAL DE ENTRADAS";
} elseif ($tipo == 'saida') {
    $where_tipo = "AND h.tipo = 'SAIDA'";
    $titulo_doc = "FATURA MENSAL DE SAÍDAS";
} else {
    $titulo_doc = "RELATÓRIO MENSAL COMPLETO";
}

// Buscar todos os movimentos do período
$sql = "
    SELECT 
        h.n_cab as doc_id,
        h.tipo,
        h.cliente,
        h.data,
        l.n_linha,
        p.descricao as produto,
        c.descricao as categoria,
        l.quantidade,
        l.preço as preco_unit,
        (l.quantidade * l.preço) as subtotal
    FROM (
        SELECT n_cab, cliente, data, 'ENTRADA' as tipo FROM ent_cab
        UNION ALL
        SELECT n_cab, cliente, data, 'SAIDA' as tipo FROM sai_cab
    ) h
    JOIN linhas l ON h.n_cab = l.id
    LEFT JOIN produtos p ON l.id_produto = p.id_produto
    LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
    WHERE DATE(h.data) BETWEEN '$data_inicio' AND '$data_fim'
    $where_tipo
    ORDER BY h.data DESC, h.n_cab, l.n_linha
";

$result = $conn->query($sql);

// Calcular totais
$total_entradas = 0;
$total_saidas = 0;
$total_quantidade = 0;
$movimentos_data = [];

while ($row = $result->fetch_assoc()) {
    $movimentos_data[] = $row;
    $total_quantidade += $row['quantidade'];
    
    if ($row['tipo'] == 'ENTRADA') {
        $total_entradas += $row['subtotal'];
    } else {
        $total_saidas += $row['subtotal'];
    }
}

$total_saldo = $total_saidas - $total_entradas;

// Iniciar buffer de saída
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo_doc; ?> - <?php echo $nome_mes; ?> <?php echo $ano; ?> - <?php echo $nome_empresa; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            padding: 20px;
            font-size: 12px;
            color: #333;
        }
        
        .fatura-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .fatura-header {
            background: linear-gradient(135deg, #bc6ff1 0%, #8e44ad 100%);
            padding: 30px 40px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .fatura-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .fatura-logo i {
            font-size: 3rem;
            opacity: 0.9;
        }
        
        .fatura-logo h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -1px;
        }
        
        .fatura-logo .t-letter { color: white !important; }
        .fatura-logo .store-text { color: rgba(255,255,255,0.9) !important; }
        
        .fatura-info {
            text-align: right;
        }
        
        .fatura-info .doc-tipo {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .fatura-info .doc-periodo {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .fatura-info .doc-data {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        .fatura-body {
            padding: 40px;
        }
        
        .fatura-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .fatura-section h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .fatura-section p {
            font-size: 0.95rem;
            margin-bottom: 8px;
            color: #555;
        }
        
        .fatura-section .label {
            font-weight: 500;
            color: #333;
        }
        
        .fatura-section .value {
            color: #666;
        }
        
        .fatura-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .fatura-table thead {
            background: #f8f9fa;
        }
        
        .fatura-table th {
            text-align: left;
            padding: 12px 15px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            font-weight: 600;
            border-bottom: 2px solid #bc6ff1;
        }
        
        .fatura-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .fatura-table .doc-id {
            font-weight: 600;
            color: #bc6ff1;
        }
        
        .fatura-table .tipo {
            font-size: 0.85rem;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .fatura-table .tipo.entrada {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .fatura-table .tipo.saida {
            background: rgba(255, 75, 43, 0.1);
            color: #ff4b2b;
        }
        
        .fatura-table .produto {
            font-weight: 500;
            color: #333;
        }
        
        .fatura-table .categoria {
            font-size: 0.85rem;
            color: #888;
        }
        
        .fatura-table .quantidade {
            text-align: center;
            font-weight: 500;
        }
        
        .fatura-table .preco {
            text-align: right;
            font-weight: 500;
        }
        
        .fatura-table .subtotal {
            text-align: right;
            font-weight: 600;
            color: #bc6ff1;
        }
        
        .fatura-totals {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .fatura-totals-box {
            width: 350px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .fatura-totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }
        
        .fatura-totals-row .label {
            color: #666;
        }
        
        .fatura-totals-row .value {
            font-weight: 500;
            color: #333;
        }
        
        .fatura-totals-row .value.entrada {
            color: #10b981;
        }
        
        .fatura-totals-row .value.saida {
            color: #ff4b2b;
        }
        
        .fatura-totals-row.total {
            border-top: 2px solid #bc6ff1;
            padding-top: 12px;
            margin-top: 12px;
            margin-bottom: 0;
        }
        
        .fatura-totals-row.total .label {
            font-weight: 600;
            color: #bc6ff1;
            font-size: 1rem;
        }
        
        .fatura-totals-row.total .value {
            font-weight: 700;
            color: #bc6ff1;
            font-size: 1.2rem;
        }
        
        .fatura-footer {
            background: #f8f9fa;
            padding: 20px 40px;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .fatura-footer p {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 5px;
        }
        
        .fatura-footer .gerado {
            color: #bc6ff1;
            font-weight: 500;
        }
        
        @media print {
            body { background: white !important; padding: 0 !important; }
            .fatura-container { box-shadow: none !important; border-radius: 0 !important; border: 1px solid #ddd !important; }
            .fatura-header {
                background: #8e44ad !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .fatura-logo h1 { color: white !important; }
            .fatura-logo .t-letter { color: white !important; }
            .fatura-logo .store-text { color: rgba(255,255,255,0.9) !important; }
            .fatura-info .doc-tipo { color: rgba(255,255,255,0.9) !important; }
            .fatura-info .doc-periodo { color: white !important; }
            .fatura-info .doc-data { color: rgba(255,255,255,0.8) !important; }
            .fatura-table th {
                background: #8e44ad !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .fatura-table .tipo.entrada {
                background: rgba(16, 185, 129, 0.2) !important;
                color: #10b981 !important;
            }
            .fatura-table .tipo.saida {
                background: rgba(255, 75, 43, 0.2) !important;
                color: #ff4b2b !important;
            }
            .fatura-table .subtotal { color: #8e44ad !important; }
            .fatura-table .doc-id { color: #8e44ad !important; }
            .fatura-totals-row.total .label { color: #8e44ad !important; }
            .fatura-totals-row.total .value { color: #8e44ad !important; }
            .fatura-totals-box { border: 1px solid #8e44ad !important; }
            .fatura-totals-row.total { border-top: 2px solid #8e44ad !important; }
            .fatura-footer .gerado { color: #8e44ad !important; }
        }
    </style>
</head>
<body>
    <div class="fatura-container">
        <!-- Header -->
        <div class="fatura-header">
            <div class="fatura-logo">
                <i class="fas fa-ghost"></i>
                <div>
                    <h1><span class="t-letter">T</span><span class="store-text">STORE</span></h1>
                </div>
            </div>
            <div class="fatura-info">
                <div class="doc-tipo"><?php echo $titulo_doc; ?></div>
                <div class="doc-periodo"><?php echo $nome_mes; ?> <?php echo $ano; ?></div>
                <div class="doc-data"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?></div>
            </div>
        </div>
        
        <!-- Body -->
        <div class="fatura-body">
            <div class="fatura-grid">
                <div class="fatura-section">
                    <h3>Resumo do Período</h3>
                    <p><span class="label">Mês:</span> <span class="value"><?php echo $nome_mes; ?> <?php echo $ano; ?></span></p>
                    <p><span class="label">Total de Movimentos:</span> <span class="value"><?php echo count($movimentos_data); ?></span></p>
                    <p><span class="label">Quantidade Total:</span> <span class="value"><?php echo $total_quantidade; ?> unid.</span></p>
                </div>
                <div class="fatura-section">
                    <h3>Metadados</h3>
                    <p><span class="label">Tipo:</span> <span class="value"><?php echo strtoupper($tipo); ?></span></p>
                    <p><span class="label">Data de Geração:</span> <span class="value"><?php echo date('d/m/Y H:i'); ?></span></p>
                    <p><span class="label">Gerado por:</span> <span class="value"><?php echo htmlspecialchars($_SESSION['user']); ?></span></p>
                </div>
            </div>
            
            <!-- Tabela de Movimentos -->
            <table class="fatura-table">
                <thead>
                    <tr>
                        <th>Doc ID</th>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Qtd</th>
                        <th>Preço Unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($movimentos_data)): ?>
                        <?php foreach ($movimentos_data as $mov): ?>
                        <tr>
                            <td><span class="doc-id">#<?php echo str_pad($mov['doc_id'], 6, '0', STR_PAD_LEFT); ?></span></td>
                            <td><span class="tipo <?php echo strtolower($mov['tipo']); ?>"><?php echo $mov['tipo']; ?></span></td>
                            <td><?php echo htmlspecialchars($mov['cliente']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($mov['data'])); ?></td>
                            <td><span class="produto"><?php echo htmlspecialchars($mov['produto']); ?></span></td>
                            <td><span class="categoria"><?php echo htmlspecialchars($mov['categoria']); ?></span></td>
                            <td class="quantidade"><?php echo $mov['quantidade']; ?></td>
                            <td class="preco"><?php echo number_format($mov['preco_unit'], 2); ?> <?php echo $moeda; ?></td>
                            <td class="subtotal"><?php echo number_format($mov['subtotal'], 2); ?> <?php echo $moeda; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #888;">Sem movimentos neste período.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Totais -->
            <div class="fatura-totals">
                <div class="fatura-totals-box">
                    <?php if ($tipo == 'ambos' || $tipo == 'entrada'): ?>
                    <div class="fatura-totals-row">
                        <span class="label">Total Entradas:</span>
                        <span class="value entrada"><?php echo number_format($total_entradas, 2); ?> <?php echo $moeda; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($tipo == 'ambos' || $tipo == 'saida'): ?>
                    <div class="fatura-totals-row">
                        <span class="label">Total Saídas:</span>
                        <span class="value saida"><?php echo number_format($total_saidas, 2); ?> <?php echo $moeda; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($tipo == 'ambos'): ?>
                    <div class="fatura-totals-row">
                        <span class="label">Saldo (Saídas - Entradas):</span>
                        <span class="value"><?php echo number_format($total_saldo, 2); ?> <?php echo $moeda; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="fatura-totals-row total">
                        <span class="label">Total Geral (Movimentado):</span>
                        <span class="value"><?php 
                            if ($tipo == 'entrada') {
                                echo number_format($total_entradas, 2);
                            } elseif ($tipo == 'saida') {
                                echo number_format($total_saidas, 2);
                            } else {
                                echo number_format($total_entradas + $total_saidas, 2);
                            }
                        ?> <?php echo $moeda; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="fatura-footer">
            <p><?php echo $nome_empresa; ?> - Sistema de Gestão Profissional</p>
            <p>Gerado por <span class="gerado"><?php echo htmlspecialchars($_SESSION['user']); ?></span> em <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>

    <script>
        // Imprimir automaticamente quando a página carrega
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
<?php
$html = ob_get_clean();
echo $html;
?>
