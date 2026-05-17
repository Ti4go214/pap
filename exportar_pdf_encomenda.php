<?php
/**
 * @file exportar_pdf_encomenda.php
 * @brief Exportar detalhe de encomenda para PDF profissional
 * @author Antigravity
 * @date 2026-05-17
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';

if (!isset($_SESSION['user'])) {
    die("Acesso negado.");
}

$id_encomenda = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_encomenda <= 0) {
    die("Encomenda não especificada.");
}

// Buscar dados da encomenda e cliente
$stmt = $conn->prepare("
    SELECT e.*, 
           c.nome as cliente_nome, 
           c.email as cliente_email, 
           c.telefone as cliente_telefone, 
           c.nif as cliente_nif, 
           c.morada as cliente_morada, 
           c.localidade as cliente_cidade
    FROM encomendas e
    LEFT JOIN clientes c ON e.id_cliente = c.id_cliente
    WHERE e.id_encomenda = ?
");
$stmt->bind_param("i", $id_encomenda);
$stmt->execute();
$encomenda = $stmt->get_result()->fetch_assoc();

if (!$encomenda) {
    die("Encomenda não encontrada.");
}

// Buscar linhas da encomenda
$stmt_lines = $conn->prepare("
    SELECT el.*, p.descricao as produto_descricao, p.id_produto
    FROM encomendas_linhas el
    JOIN produtos p ON el.id_produto = p.id_produto
    WHERE el.id_encomenda = ?
    ORDER BY el.id_linha ASC
");
$stmt_lines->bind_param("i", $id_encomenda);
$stmt_lines->execute();
$linhas = $stmt_lines->get_result()->fetch_all(MYSQLI_ASSOC);

// Carregar footer das definições globais
$footer_text = !empty($settings['footer_text']) ? $settings['footer_text'] : "© 2026 TSTORE • Gestão Profissional";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Encomenda_<?php echo $encomenda['num_encomenda']; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            background-color: #fff;
            line-height: 1.5;
            padding: 40px;
            font-size: 0.9rem;
        }

        /* Botões de Controlo do Browser */
        .no-print-bar {
            background: #1e0f32;
            padding: 15px 40px;
            margin: -40px -40px 40px -40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #bc6ff1;
        }
        
        .no-print-bar h3 {
            color: #fff;
            font-weight: 500;
            font-size: 1.1rem;
        }

        .btn-print {
            background: #bc6ff1;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-print:hover {
            background: #a55cd9;
            transform: translateY(-1px);
        }

        /* Container do Documento */
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        /* Cabeçalho da Fatura */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 30px;
            margin-bottom: 30px;
        }

        .company-logo {
            color: #bc6ff1;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .company-sub {
            color: #6b7280;
            font-size: 0.85rem;
            margin-top: 3px;
        }

        .document-title {
            text-align: right;
        }

        .document-title h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .document-details {
            color: #4b5563;
            font-size: 0.9rem;
            text-align: right;
        }

        .document-details p {
            margin-bottom: 3px;
        }

        /* Secção de Clientes e Detalhes da Encomenda */
        .info-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .info-section h4 {
            font-size: 0.8rem;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }

        .info-section p {
            margin-bottom: 4px;
            color: #1f2937;
        }

        .info-section .name {
            font-size: 1.05rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 6px;
        }

        /* Badge de Estado */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-pendente { background: #fef3c7; color: #d97706; }
        .badge-processamento { background: #dbeafe; color: #2563eb; }
        .badge-enviado { background: #e0e7ff; color: #4f46e5; }
        .badge-entregue { background: #d1fae5; color: #059669; }
        .badge-cancelado { background: #fee2e2; color: #dc2626; }

        /* Separador */
        .divider {
            height: 0;
            border: none;
            border-top: 2px dashed #9ca3af; /* Cinzento escuro para máximo contraste */
            margin: 40px 0;
            width: 100%;
        }

        /* Tabela de Produtos */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .invoice-table th {
            background: #f9fafb;
            color: #4b5563;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
        }

        .invoice-table td {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }

        .invoice-table th.align-right,
        .invoice-table td.align-right {
            text-align: right;
        }

        .invoice-table td.total-col {
            font-weight: 600;
            color: #111827;
        }

        /* Totais */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            page-break-inside: avoid;
        }

        .totals-table {
            width: 320px;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px 16px;
            color: #4b5563;
            font-size: 0.95rem;
        }

        .totals-table tr.total-row td {
            font-weight: 700;
            font-size: 1.2rem;
            color: #bc6ff1;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
        }

        /* Rodapé */
        .invoice-footer {
            margin-top: 60px;
            text-align: center;
            border-top: 1px solid #f3f4f6;
            padding-top: 20px;
            color: #9ca3af;
            font-size: 0.8rem;
            page-break-inside: avoid;
        }

        .invoice-footer p {
            margin-bottom: 4px;
        }

        /* Estilos de Impressão */
        @media print {
            body {
                padding: 0;
                background-color: #fff;
            }
            .no-print-bar {
                display: none;
            }
            .invoice-container {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            .invoice-table th {
                background: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .divider {
                border-top: 2px dashed #9ca3af !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge-pendente { background: #fef3c7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-processamento { background: #dbeafe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-enviado { background: #e0e7ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-entregue { background: #d1fae5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-cancelado { background: #fee2e2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <!-- Barra Superior (Não é impressa) -->
    <div class="no-print-bar">
        <h3>📄 Pré-visualização de Fatura / Encomenda</h3>
        <button class="btn-print" onclick="window.print()">
            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-3a2 2 0 00-2-2H9a2 2 0 00-2 2v3a2 2 0 002 2zm5-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h8z"></path>
            </svg>
            Imprimir ou Guardar PDF
        </button>
    </div>

    <!-- Bloco da Fatura -->
    <div class="invoice-container">
        <!-- Cabeçalho -->
        <div class="invoice-header">
            <div>
                <div class="company-logo">TSTORE</div>
                <div class="company-sub">Gestão Profissional de Stocks</div>
            </div>
            
            <div class="document-title">
                <h2>Encomenda</h2>
                <div class="document-details">
                    <p><strong>Nº:</strong> <?php echo htmlspecialchars($encomenda['num_encomenda']); ?></p>
                    <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($encomenda['data_encomenda'])); ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge badge-<?php echo $encomenda['estado']; ?>">
                            <?php echo $encomenda['estado']; ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Informação do Cliente & Documento -->
        <div class="info-grid">
            <div class="info-section">
                <h4>Faturar A:</h4>
                <p class="name"><?php echo htmlspecialchars($encomenda['cliente_nome']); ?></p>
                <?php if (!empty($encomenda['cliente_nif'])): ?>
                    <p><strong>NIF:</strong> <?php echo htmlspecialchars($encomenda['cliente_nif']); ?></p>
                <?php endif; ?>
                <?php if (!empty($encomenda['cliente_email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($encomenda['cliente_email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($encomenda['cliente_telefone'])): ?>
                    <p><strong>Telefone:</strong> <?php echo htmlspecialchars($encomenda['cliente_telefone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($encomenda['cliente_morada']) || !empty($encomenda['cliente_cidade'])): ?>
                    <p><strong>Morada:</strong> 
                        <?php 
                        $morada_completa = array_filter([$encomenda['cliente_morada'], $encomenda['cliente_cidade']]);
                        echo htmlspecialchars(implode(', ', $morada_completa)); 
                        ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="info-section">
                <h4>Notas e Observações:</h4>
                <p style="font-style: italic; color: #4b5563; white-space: pre-wrap;"><?php echo !empty($encomenda['observacoes']) ? htmlspecialchars($encomenda['observacoes']) : 'Sem observações adicionais.'; ?></p>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Tabela de Produtos -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Produto / Descrição</th>
                    <th class="align-right">Qtd</th>
                    <th class="align-right">Preço Unit.</th>
                    <th class="align-right">IVA (%)</th>
                    <th class="align-right">Total (com IVA)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linhas as $lin): ?>
                    <?php 
                    $subtotal_bruto = $lin['preco_unitario'] * $lin['quantidade'];
                    $taxa_iva = (float)$lin['taxa_iva'];
                    $valor_iva = $subtotal_bruto * ($taxa_iva / 100);
                    $total_linha = $subtotal_bruto + $valor_iva;
                    ?>
                    <tr>
                        <td style="color: #6b7280; font-family: monospace;">#PROD_<?php echo $lin['id_produto']; ?></td>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($lin['produto_descricao']); ?></td>
                        <td class="align-right"><?php echo $lin['quantidade']; ?></td>
                        <td class="align-right"><?php echo number_format($lin['preco_unitario'], 2, ',', '.'); ?> €</td>
                        <td class="align-right"><?php echo number_format($taxa_iva, 0); ?>%</td>
                        <td class="align-right total-col"><?php echo number_format($total_linha, 2, ',', '.'); ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <!-- Totais -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal (Base Bruta):</td>
                    <td class="align-right"><?php echo number_format($encomenda['total_bruto'], 2, ',', '.'); ?> €</td>
                </tr>
                <tr>
                    <td>Total IVA:</td>
                    <td class="align-right"><?php echo number_format($encomenda['total_iva'], 2, ',', '.'); ?> €</td>
                </tr>
                <?php if ($encomenda['desconto_percent'] > 0): ?>
                    <tr style="color: #fbbf24; font-weight: 500;">
                        <td>Desconto Fidelização (<?php echo number_format($encomenda['desconto_percent'], 0); ?>%):</td>
                        <td class="align-right">-<?php echo number_format(($encomenda['total_bruto'] + $encomenda['total_iva']) * ($encomenda['desconto_percent'] / 100), 2, ',', '.'); ?> €</td>
                    </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td>Total Líquido:</td>
                    <td class="align-right"><?php echo number_format($encomenda['total_liquido'], 2, ',', '.'); ?> €</td>
                </tr>
            </table>
        </div>

        <!-- Rodapé -->
        <div class="invoice-footer">
            <p>Obrigado pela sua preferência!</p>
            <p><?php echo htmlspecialchars($footer_text); ?></p>
        </div>
    </div>

    <!-- Script para abrir a janela de impressão automaticamente -->
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 300);
        }
    </script>
</body>
</html>
