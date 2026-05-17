<?php
/**
 * @file exportar_pdf_enc_fornecedor.php
 * @brief Exportar encomenda a fornecedor para PDF profissional (sem IVA)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include 'includes/db_connect.php';

if (!isset($_SESSION['user'])) die("Acesso negado.");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Encomenda não especificada.");

$stmt = $conn->prepare("
    SELECT ef.*, 
           f.nome as forn_nome, f.nif as forn_nif, f.email as forn_email, 
           f.telefone as forn_telefone, f.morada as forn_morada, 
           f.localidade as forn_localidade, f.contacto_principal
    FROM encomendas_fornecedores ef
    LEFT JOIN fornecedores f ON ef.id_fornecedor = f.id_fornecedor
    WHERE ef.id_enc_fornecedor = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$enc = $stmt->get_result()->fetch_assoc();
if (!$enc) die("Encomenda não encontrada.");

$stmt2 = $conn->prepare("
    SELECT efl.*, p.descricao as produto_descricao, p.id_produto
    FROM encomendas_fornecedores_linhas efl
    JOIN produtos p ON efl.id_produto = p.id_produto
    WHERE efl.id_enc_fornecedor = ?
    ORDER BY efl.id_linha ASC
");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$linhas = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Enc_Fornecedor_<?php echo $enc['num_encomenda']; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; color: #333; background: #fff; line-height: 1.5; padding: 40px; font-size: 0.9rem; }

        .no-print-bar { background: #0f1f1a; padding: 15px 40px; margin: -40px -40px 40px -40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #10b981; }
        .no-print-bar h3 { color: #fff; font-weight: 500; font-size: 1.1rem; }
        .btn-print { background: #10b981; color: #fff; border: none; padding: 10px 20px; font-size: 0.9rem; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-print:hover { background: #059669; transform: translateY(-1px); }

        .invoice-container { max-width: 800px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f3f4f6; padding-bottom: 30px; margin-bottom: 30px; }
        .company-logo { color: #10b981; font-size: 1.8rem; font-weight: 700; letter-spacing: -0.5px; }
        .company-sub { color: #6b7280; font-size: 0.85rem; margin-top: 3px; }
        .document-title { text-align: right; }
        .document-title h2 { font-size: 1.6rem; font-weight: 700; color: #111827; margin-bottom: 5px; text-transform: uppercase; }
        .document-details { color: #4b5563; font-size: 0.9rem; text-align: right; }
        .document-details p { margin-bottom: 3px; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .badge-pendente { background: #fef3c7; color: #d97706; }
        .badge-enviado { background: #dbeafe; color: #2563eb; }
        .badge-recebido { background: #d1fae5; color: #059669; }
        .badge-cancelado { background: #fee2e2; color: #dc2626; }

        .info-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px; margin-bottom: 30px; }
        .info-section h4 { font-size: 0.8rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        .info-section p { margin-bottom: 4px; color: #1f2937; }
        .info-section .name { font-size: 1.05rem; font-weight: 600; color: #111827; margin-bottom: 6px; }

        .divider { height: 0; border: none; border-top: 2px dashed #9ca3af; margin: 30px 0; width: 100%; }

        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .invoice-table th { background: #f0fdf4; color: #4b5563; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 12px 16px; text-align: left; border-bottom: 2px solid #10b981; }
        .invoice-table td { padding: 16px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        .invoice-table th.align-right, .invoice-table td.align-right { text-align: right; }
        .invoice-table td.total-col { font-weight: 600; color: #111827; }

        .totals-section { display: flex; justify-content: flex-end; margin-top: 20px; }
        .totals-table { width: 300px; border-collapse: collapse; }
        .totals-table td { padding: 8px 16px; color: #4b5563; font-size: 0.95rem; }
        .totals-table tr.total-row td { font-weight: 700; font-size: 1.2rem; color: #10b981; padding-top: 15px; border-top: 2px solid #e5e7eb; }

        .invoice-footer { margin-top: 60px; text-align: center; border-top: 1px solid #f3f4f6; padding-top: 20px; color: #9ca3af; font-size: 0.8rem; }
        .invoice-footer p { margin-bottom: 4px; }

        @media print {
            body { padding: 0; }
            .no-print-bar { display: none; }
            .invoice-container { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .invoice-table th { background: #f0fdf4 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-pendente { background: #fef3c7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-enviado { background: #dbeafe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-recebido { background: #d1fae5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-cancelado { background: #fee2e2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .divider { border-top: 2px dashed #9ca3af !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <h3>📦 Pré-visualização — Encomenda a Fornecedor</h3>
        <button class="btn-print" onclick="window.print()">
            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-3a2 2 0 00-2-2H9a2 2 0 00-2 2v3a2 2 0 002 2zm5-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h8z"/>
            </svg>
            Imprimir ou Guardar PDF
        </button>
    </div>

    <div class="invoice-container">
        <div class="invoice-header">
            <div>
                <div class="company-logo">TSTORE</div>
                <div class="company-sub">Nota de Encomenda — Compra a Fornecedor</div>
            </div>
            <div class="document-title">
                <h2>Encomenda</h2>
                <div class="document-details">
                    <p><strong>Nº:</strong> <?php echo htmlspecialchars($enc['num_encomenda']); ?></p>
                    <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($enc['data_encomenda'])); ?></p>
                    <p><strong>Estado:</strong> <span class="badge badge-<?php echo $enc['estado']; ?>"><?php echo $enc['estado']; ?></span></p>
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-section">
                <h4>Fornecedor:</h4>
                <p class="name"><?php echo htmlspecialchars($enc['forn_nome']); ?></p>
                <?php if (!empty($enc['forn_nif'])): ?><p><strong>NIF:</strong> <?php echo htmlspecialchars($enc['forn_nif']); ?></p><?php endif; ?>
                <?php if (!empty($enc['forn_email'])): ?><p><strong>Email:</strong> <?php echo htmlspecialchars($enc['forn_email']); ?></p><?php endif; ?>
                <?php if (!empty($enc['forn_telefone'])): ?><p><strong>Telefone:</strong> <?php echo htmlspecialchars($enc['forn_telefone']); ?></p><?php endif; ?>
                <?php if (!empty($enc['contacto_principal'])): ?><p><strong>Contacto:</strong> <?php echo htmlspecialchars($enc['contacto_principal']); ?></p><?php endif; ?>
                <?php if (!empty($enc['forn_morada']) || !empty($enc['forn_localidade'])): ?>
                    <p><strong>Morada:</strong> <?php echo htmlspecialchars(implode(', ', array_filter([$enc['forn_morada'], $enc['forn_localidade']]))); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-section">
                <h4>Observações:</h4>
                <p style="font-style: italic; color: #4b5563; white-space: pre-wrap;"><?php echo !empty($enc['observacoes']) ? htmlspecialchars($enc['observacoes']) : 'Sem observações adicionais.'; ?></p>
            </div>
        </div>

        <div class="divider"></div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Produto / Descrição</th>
                    <th class="align-right">Qtd</th>
                    <th class="align-right">Preço Unit. (s/ IVA)</th>
                    <th class="align-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linhas as $lin): ?>
                    <?php $subtotal = $lin['preco_unitario'] * $lin['quantidade']; ?>
                    <tr>
                        <td style="color: #6b7280; font-family: monospace;">#PROD_<?php echo $lin['id_produto']; ?></td>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($lin['produto_descricao']); ?></td>
                        <td class="align-right"><?php echo $lin['quantidade']; ?></td>
                        <td class="align-right"><?php echo number_format($lin['preco_unitario'], 2, ',', '.'); ?> €</td>
                        <td class="align-right total-col"><?php echo number_format($subtotal, 2, ',', '.'); ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="totals-section">
            <table class="totals-table">
                <tr class="total-row">
                    <td>Total (s/ IVA):</td>
                    <td class="align-right"><?php echo number_format($enc['total_bruto'], 2, ',', '.'); ?> €</td>
                </tr>
            </table>
        </div>

        <div class="invoice-footer">
            <p>Documento emitido em <?php echo date('d/m/Y H:i'); ?> — TSTORE</p>
        </div>
    </div>

    <script>
        window.onload = function() { setTimeout(function() { window.print(); }, 300); }
    </script>
</body>
</html>
