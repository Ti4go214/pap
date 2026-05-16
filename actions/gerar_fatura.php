<?php
/**
 * @file gerar_fatura.php
 * @brief Sistema de Geração de Faturas PDF Profissionais - TSTORE
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
$tipo = $_GET['tipo'] ?? 'saida'; // 'entrada' ou 'saida'
$id_doc = $_GET['id'] ?? 0;

// Validar parâmetros
if (!in_array($tipo, ['entrada', 'saida']) || $id_doc <= 0) {
    die("Parâmetros inválidos");
}

// Determinar tabela e prefixo
$tabela_cab = ($tipo == 'entrada') ? 'ent_cab' : 'sai_cab';
$titulo_doc = ($tipo == 'entrada') ? 'NOTA DE ENTRADA' : 'FATURA DE SAÍDA';
$cor_titulo = ($tipo == 'entrada') ? '#10b981' : '#ff4b2b';

// Buscar dados do cabeçalho
$sql_cab = "SELECT * FROM $tabela_cab WHERE n_cab = ?";
$stmt = $conn->prepare($sql_cab);
$stmt->bind_param("i", $id_doc);
$stmt->execute();
$result_cab = $stmt->get_result();

if ($result_cab->num_rows == 0) {
    die("Documento não encontrado");
}

$cab = $result_cab->fetch_assoc();

// Buscar linhas do documento
$sql_linhas = "SELECT l.*, p.descricao as prod_nome, c.descricao as cat_nome
               FROM linhas l 
               LEFT JOIN produtos p ON l.id_produto = p.id_produto 
               LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
               WHERE l.id = ?
               ORDER BY l.n_linha";
$stmt = $conn->prepare($sql_linhas);
$stmt->bind_param("i", $id_doc);
$stmt->execute();
$result_linhas = $stmt->get_result();

// Calcular totais
$total_quantidade = 0;
$total_valor = 0;
$linhas_data = [];

while ($linha = $result_linhas->fetch_assoc()) {
    $subtotal = $linha['quantidade'] * $linha['preço'];
    $total_quantidade += $linha['quantidade'];
    $total_valor += $subtotal;
    $linhas_data[] = [
        'produto' => $linha['prod_nome'] ?? 'Produto #' . $linha['id_produto'],
        'categoria' => $linha['cat_nome'] ?? '-',
        'quantidade' => $linha['quantidade'],
        'preco' => $linha['preço'],
        'subtotal' => $subtotal
    ];
}

// Configurações da empresa
$nome_empresa = $config['app_name'] ?? 'TSTORE';
$moeda = $config['currency'] ?? '€';
$data_doc = date('d/m/Y H:i', strtotime($cab['data']));
$cliente = $cab['cliente'];

// Determinar se é para download ou visualização
$download = $_GET['download'] ?? false;

// Iniciar buffer de saída
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo_doc; ?> #<?php echo $id_doc; ?> - <?php echo $nome_empresa; ?></title>
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
            max-width: 800px;
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
        
        .fatura-info .doc-numero {
            font-size: 1.8rem;
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
            width: 300px;
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
            .fatura-info .doc-numero { color: white !important; }
            .fatura-info .doc-data { color: rgba(255,255,255,0.8) !important; }
            .fatura-table th {
                background: #8e44ad !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .fatura-table .subtotal { color: #8e44ad !important; }
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
                <div class="doc-numero">#<?php echo str_pad($id_doc, 6, '0', STR_PAD_LEFT); ?></div>
                <div class="doc-data"><i class="fas fa-calendar-alt"></i> <?php echo $data_doc; ?></div>
            </div>
        </div>
        
        <!-- Body -->
        <div class="fatura-body">
            <div class="fatura-grid">
                <div class="fatura-section">
                    <h3>Informações do Documento</h3>
                    <p><span class="label">Tipo:</span> <span class="value"><?php echo $titulo_doc; ?></span></p>
                    <p><span class="label">Número:</span> <span class="value">#<?php echo str_pad($id_doc, 6, '0', STR_PAD_LEFT); ?></span></p>
                    <p><span class="label">Data:</span> <span class="value"><?php echo $data_doc; ?></span></p>
                </div>
                <div class="fatura-section">
                    <h3>Entidade</h3>
                    <p><span class="label">Cliente/Fornecedor:</span> <span class="value"><?php echo htmlspecialchars($cliente); ?></span></p>
                    <p><span class="label">Referência:</span> <span class="value"><?php echo $id_doc; ?></span></p>
                </div>
            </div>
            
            <!-- Tabela de Itens -->
            <table class="fatura-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Quantidade</th>
                        <th>Preço Unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linhas_data as $linha): ?>
                    <tr>
                        <td>
                            <div class="produto"><?php echo htmlspecialchars($linha['produto']); ?></div>
                        </td>
                        <td><span class="categoria"><?php echo htmlspecialchars($linha['categoria']); ?></span></td>
                        <td class="quantidade"><?php echo $linha['quantidade']; ?></td>
                        <td class="preco"><?php echo number_format($linha['preco'], 2); ?> <?php echo $moeda; ?></td>
                        <td class="subtotal"><?php echo number_format($linha['subtotal'], 2); ?> <?php echo $moeda; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Totais -->
            <div class="fatura-totals">
                <div class="fatura-totals-box">
                    <div class="fatura-totals-row">
                        <span class="label">Total de Itens:</span>
                        <span class="value"><?php echo count($linhas_data); ?></span>
                    </div>
                    <div class="fatura-totals-row">
                        <span class="label">Quantidade Total:</span>
                        <span class="value"><?php echo $total_quantidade; ?> unid.</span>
                    </div>
                    <div class="fatura-totals-row total">
                        <span class="label">Total:</span>
                        <span class="value"><?php echo number_format($total_valor, 2); ?> <?php echo $moeda; ?></span>
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
            // Pequeno delay para garantir que a página está completamente carregada
            setTimeout(function() {
                window.print();
            }, 500);
        };

        // Fechar janela após impressão (opcional)
        window.onafterprint = function() {
            // Se foi aberta numa nova janela, pode fechar automaticamente
            if (window.opener && !window.opener.closed) {
                // Não fecha automaticamente para permitir que o utilizador guarde como PDF
            }
        };
    </script>
</body>
</html>
<?php

$html = ob_get_clean();

// Se for para download, enviar headers apropriados
if ($download) {
    $filename = "{$titulo_doc}_#{$id_doc}_{$nome_empresa}_" . date('Ymd_His') . '.html';
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($html));
    header('Pragma: no-cache');
    header('Expires: 0');
}

echo $html;
?>
