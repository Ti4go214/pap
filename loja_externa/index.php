<?php
/**
 * @file index.php
 * @brief Loja Externa Independente (E-Commerce B2C em Tema Claro)
 * @author Antigravity
 * @date 2026-05-17
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ligar à base de dados principal da aplicação (TSTORE)
include '../includes/db_connect.php';

// Garantir que a base de dados tem as colunas necessárias
$res_c = $conn->query("SHOW COLUMNS FROM clientes LIKE 'desconto_pendente'");
if ($res_c && $res_c->num_rows === 0) {
    $conn->query("ALTER TABLE clientes ADD COLUMN desconto_pendente TINYINT(1) NOT NULL DEFAULT 0");
}
$res_p = $conn->query("SHOW COLUMNS FROM clientes LIKE 'password_hash'");
if ($res_p && $res_p->num_rows === 0) {
    $conn->query("ALTER TABLE clientes ADD COLUMN password_hash VARCHAR(255) NULL");
}
$res_e = $conn->query("SHOW COLUMNS FROM encomendas LIKE 'desconto_percent'");
if ($res_e && $res_e->num_rows === 0) {
    $conn->query("ALTER TABLE encomendas ADD COLUMN desconto_percent DECIMAL(5,2) NOT NULL DEFAULT 0");
}

// Inicializar carrinho da loja externa na sessão se não existir
if (!isset($_SESSION['store_cart'])) {
    $_SESSION['store_cart'] = [];
}

// Processar pedidos AJAX (Adicionar, Remover, Atualizar Carrinho, Checkout)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'add') {
        $id_produto = (int)($_POST['id_produto'] ?? 0);
        $quantidade = (int)($_POST['quantidade'] ?? 1);

        if ($id_produto > 0 && $quantidade > 0) {
            $stmt = $conn->prepare("SELECT p.descricao, p.preco_unit, p.quantidade as stock, t.taxa as taxa_iva, p.imagem 
                                    FROM produtos p 
                                    LEFT JOIN categoria c ON p.id_categoria = c.id_categoria 
                                    LEFT JOIN iva_taxas t ON c.id_iva = t.id_taxa 
                                    WHERE p.id_produto = ?");
            $stmt->bind_param("i", $id_produto);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($prod = $res->fetch_assoc()) {
                if ($prod['stock'] < $quantidade) {
                    echo json_encode(['success' => false, 'message' => "Stock insuficiente. Apenas " . $prod['stock'] . " unidades disponíveis."]);
                    exit;
                }

                $taxa_iva = (float)($prod['taxa_iva'] ?? 23);
                $preco_unit = (float)$prod['preco_unit'];

                // Verificar se já existe no carrinho
                $encontrado = false;
                foreach ($_SESSION['store_cart'] as &$item) {
                    if ($item['id_produto'] === $id_produto) {
                        if ($item['quantidade'] + $quantidade > $prod['stock']) {
                            echo json_encode(['success' => false, 'message' => "Não é possível adicionar mais unidades. Stock máximo atingido."]);
                            exit;
                        }
                        $item['quantidade'] += $quantidade;
                        $encontrado = true;
                        break;
                    }
                }

                if (!$encontrado) {
                    $_SESSION['store_cart'][] = [
                        'id_produto' => $id_produto,
                        'descricao' => $prod['descricao'],
                        'preco_unitario' => $preco_unit,
                        'taxa_iva' => $taxa_iva,
                        'quantidade' => $quantidade,
                        'imagem' => $prod['imagem'] ?? ''
                    ];
                }

                echo json_encode(['success' => true, 'message' => "Produto adicionado ao carrinho!", 'cart_count' => array_sum(array_column($_SESSION['store_cart'], 'quantidade'))]);
                exit;
            }
            $stmt->close();
        }
        echo json_encode(['success' => false, 'message' => "Dados de produto inválidos."]);
        exit;

    } elseif ($action === 'remove') {
        $index = (int)($_POST['index'] ?? -1);
        if (isset($_SESSION['store_cart'][$index])) {
            array_splice($_SESSION['store_cart'], $index, 1);
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => "Item não encontrado."]);
        exit;

    } elseif ($action === 'update') {
        $index = (int)($_POST['index'] ?? -1);
        $quantidade = (int)($_POST['quantidade'] ?? 1);

        if (isset($_SESSION['store_cart'][$index])) {
            $id_p = $_SESSION['store_cart'][$index]['id_produto'];
            $stmt = $conn->prepare("SELECT quantidade as stock FROM produtos WHERE id_produto = ?");
            $stmt->bind_param("i", $id_p);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($prod = $res->fetch_assoc()) {
                if ($quantidade > $prod['stock']) {
                    echo json_encode(['success' => false, 'message' => "Stock insuficiente. Apenas " . $prod['stock'] . " disponíveis.", 'max_stock' => $prod['stock']]);
                    exit;
                }
                if ($quantidade <= 0) {
                    array_splice($_SESSION['store_cart'], $index, 1);
                } else {
                    $_SESSION['store_cart'][$index]['quantidade'] = $quantidade;
                }
                echo json_encode(['success' => true]);
                exit;
            }
            $stmt->close();
        }
        echo json_encode(['success' => false, 'message' => "Erro ao atualizar quantidade."]);
        exit;

    } elseif ($action === 'get_cart') {
        $total_bruto = 0;
        $total_iva = 0;
        foreach ($_SESSION['store_cart'] as $item) {
            $base = $item['preco_unitario'] * $item['quantidade'];
            $iva = $base * ($item['taxa_iva'] / 100);
            $total_bruto += $base;
            $total_iva += $iva;
        }
        $total_com_iva = $total_bruto + $total_iva;
        
        // Verificar se foi passado email para checar desconto de fidelização
        $email_check = trim($_POST['email'] ?? '');
        $tem_desconto_bonus = false;
        if (!empty($email_check)) {
            $stmt_c = $conn->prepare("SELECT desconto_pendente FROM clientes WHERE email = ? OR nome = ? LIMIT 1");
            $stmt_c->bind_param("ss", $email_check, $email_check);
            $stmt_c->execute();
            $res_c = $stmt_c->get_result();
            if ($cli = $res_c->fetch_assoc()) {
                if (($cli['desconto_pendente'] ?? 0) == 1) {
                    $tem_desconto_bonus = true;
                }
            }
            $stmt_c->close();
        }

        $desconto_val = $tem_desconto_bonus ? ($total_com_iva * 0.10) : 0;
        $total_final = $total_com_iva - $desconto_val;

        echo json_encode([
            'success' => true,
            'carrinho' => $_SESSION['store_cart'],
            'total_bruto' => number_format($total_bruto, 2, '.', ''),
            'total_iva' => number_format($total_iva, 2, '.', ''),
            'desconto_val' => number_format($desconto_val, 2, '.', ''),
            'total_final' => number_format($total_final, 2, '.', ''),
            'tem_desconto' => $tem_desconto_bonus,
            'cart_count' => array_sum(array_column($_SESSION['store_cart'], 'quantidade'))
        ]);
        exit;

    } elseif ($action === 'checkout') {
        if (empty($_SESSION['store_cart'])) {
            echo json_encode(['success' => false, 'message' => "O seu carrinho está vazio."]);
            exit;
        }

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $morada = trim($_POST['morada'] ?? '');
        $cpostal = trim($_POST['cpostal'] ?? '');
        $localidade = trim($_POST['localidade'] ?? '');
        $metodo_pagamento = $_POST['metodo_pagamento'] ?? 'Multibanco';
        $observacoes = trim($_POST['observacoes'] ?? '');

        if (!isset($_SESSION['cliente_logado'])) {
            echo json_encode(['success' => false, 'message' => "Sessão expirada. Por favor inicie sessão novamente."]);
            exit;
        }

        if (empty($telefone) || empty($morada) || empty($cpostal) || empty($localidade)) {
            echo json_encode(['success' => false, 'message' => "Por favor preencha todos os campos obrigatórios da morada."]);
            exit;
        }

        $id_cliente = (int)$_SESSION['cliente_logado'];

        $conn->begin_transaction();

        try {
            // 1. Atualizar dados de contacto/morada do cliente logado
            $stmt_upd = $conn->prepare("UPDATE clientes SET telefone=?, morada=?, cpostal=?, localidade=? WHERE id_cliente=?");
            $stmt_upd->bind_param("ssssi", $telefone, $morada, $cpostal, $localidade, $id_cliente);
            $stmt_upd->execute();
            $stmt_upd->close();

            // Verificar se tem desconto pendente
            $tem_desconto_bonus = false;
            $res_cli = $conn->query("SELECT desconto_pendente FROM clientes WHERE id_cliente=$id_cliente");
            if ($cli = $res_cli->fetch_assoc()) {
                $tem_desconto_bonus = (($cli['desconto_pendente'] ?? 0) == 1);
            }

            // 2. Calcular totais
            $total_bruto = 0;
            $total_iva = 0;
            foreach ($_SESSION['store_cart'] as $item) {
                $base = $item['preco_unitario'] * $item['quantidade'];
                $iva = $base * ($item['taxa_iva'] / 100);
                $total_bruto += $base;
                $total_iva += $iva;
            }
            $total_com_iva = $total_bruto + $total_iva;
            $desconto_percent = $tem_desconto_bonus ? 10 : 0;
            $total_liquido = $total_com_iva * (1 - ($desconto_percent / 100));

            $num_encomenda = 'EN' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $data_encomenda = date('Y-m-d');
            $estado = 'pendente';
            $obs_final = "[LOJA EXTERNA] Método Pagamento: $metodo_pagamento. " . $observacoes;

            // 3. Inserir Encomenda
            $stmt_enc = $conn->prepare("INSERT INTO encomendas (num_encomenda, id_cliente, data_encomenda, estado, observacoes, desconto_percent, total_bruto, total_iva, total_liquido) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_enc->bind_param("sisssdddd", $num_encomenda, $id_cliente, $data_encomenda, $estado, $obs_final, $desconto_percent, $total_bruto, $total_iva, $total_liquido);
            $stmt_enc->execute();
            $id_encomenda = $conn->insert_id;
            $stmt_enc->close();

            // 4. Inserir Linhas da Encomenda
            foreach ($_SESSION['store_cart'] as $item) {
                $id_p = $item['id_produto'];
                $qtd = $item['quantidade'];
                $prc = $item['preco_unitario'];
                $taxa = $item['taxa_iva'];
                $viva = ($prc * ($taxa / 100)) * $qtd;

                $stmt_lin = $conn->prepare("INSERT INTO encomendas_linhas (id_encomenda, id_produto, quantidade, preco_unitario, taxa_iva, valor_iva) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_lin->bind_param("iiiddd", $id_encomenda, $id_p, $qtd, $prc, $taxa, $viva);
                $stmt_lin->execute();
                $stmt_lin->close();
            }

            // 5. Retirar desconto pendente se usado
            if ($tem_desconto_bonus) {
                $conn->query("UPDATE clientes SET desconto_pendente=0 WHERE id_cliente=$id_cliente");
            }

            $conn->commit();

            // Registo de log (se existir função) e notificação
            if (function_exists('registarLog')) {
                registarLog(1, 'ENCOMENDA_LOJA_EXTERNA', "Encomenda Loja Externa: $num_encomenda | Cliente: $nome");
            }

            // Limpar carrinho
            $_SESSION['store_cart'] = [];

            echo json_encode(['success' => true, 'num_encomenda' => $num_encomenda, 'total' => number_format($total_liquido, 2, ',', '.')]);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => "Erro ao processar encomenda: " . $e->getMessage()]);
            exit;
        }
    }
}

// Buscar categorias para o filtro
$categorias = $conn->query("SELECT * FROM categoria ORDER BY descricao ASC");

// Processar filtros da montra
$where_clauses = ["p.quantidade > 0"]; // Apenas produtos com stock
$cat_filter = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_filter = isset($_GET['sort']) ? $_GET['sort'] : 'nome_asc';

if ($cat_filter > 0) {
    $where_clauses[] = "p.id_categoria = $cat_filter";
}
if (!empty($search_filter)) {
    $search_esc = $conn->real_escape_string($search_filter);
    $where_clauses[] = "(p.descricao LIKE '%$search_esc%' OR c.descricao LIKE '%$search_esc%')";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

$order_by = "p.descricao ASC";
if ($sort_filter === 'preco_asc') $order_by = "p.preco_unit ASC";
elseif ($sort_filter === 'preco_desc') $order_by = "p.preco_unit DESC";
elseif ($sort_filter === 'recentes') $order_by = "p.id_produto DESC";

// Consulta principal de produtos
$sql_produtos = "SELECT p.*, c.descricao as categoria_nome 
                 FROM produtos p 
                 LEFT JOIN categoria c ON p.id_categoria = c.id_categoria 
                 $where_sql 
                 ORDER BY $order_by";
$produtos = $conn->query($sql_produtos);

$cart_count = array_sum(array_column($_SESSION['store_cart'], 'quantidade'));
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja Oficial • TSTORE</title>
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Design System - Tema Claro Premium (Clean & Minimal B2C Storefront) */
        :root {
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #10b981;
            --primary-hover: #059669;
            --primary-light: #ecfdf5;
            --accent: #6366f1;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.02);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            --radius-lg: 16px;
            --radius-xl: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Top Bar / Header Principal */
        .store-header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .header-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .store-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .store-logo i {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .header-search {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        .header-search input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            background-color: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            font-size: 0.95rem;
            color: var(--text-main);
            outline: none;
            transition: all 0.3s;
        }

        .header-search input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
            background-color: var(--bg-card);
        }

        .header-search i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-cart-toggle {
            background: var(--primary-light);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--primary);
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }

        .btn-cart-toggle:hover {
            background: var(--primary);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .cart-badge-count {
            background: var(--accent);
            color: #ffffff;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 800;
        }

        /* Container Principal */
        .main-container {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 30px;
            flex: 1;
        }

        /* Hero Banner Premium */
        .hero-banner {
            background: linear-gradient(135deg, #ecfdf5 0%, #e0e7ff 100%);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 60px 50px;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            max-width: 650px;
            z-index: 10;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--primary);
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 15px;
            line-height: 1.15;
            letter-spacing: -1px;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-muted);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .hero-image-container {
            width: 280px;
            height: 280px;
            background: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-xl);
            z-index: 10;
            border: 8px solid var(--primary-light);
        }

        .hero-image-container i {
            font-size: 8rem;
            color: var(--primary);
        }

        /* Barra de Filtros de Categoria */
        .category-filters {
            display: flex;
            align-items: center;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 15px;
            margin-bottom: 35px;
            scrollbar-width: none;
        }

        .category-filters::-webkit-scrollbar {
            display: none;
        }

        .cat-pill {
            padding: 12px 24px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }

        .cat-pill:hover, .cat-pill.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.25);
            transform: translateY(-2px);
        }

        /* Grelha de Produtos */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .product-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: var(--shadow-sm);
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .product-image-box {
            height: 220px;
            background-color: var(--bg-main);
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .product-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .product-card:hover .product-image-box img {
            transform: scale(1.08);
        }

        .product-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #ffffff;
            color: var(--accent);
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            box-shadow: var(--shadow-sm);
            z-index: 10;
        }

        .product-stock {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--primary-light);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            z-index: 10;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .product-price span {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .btn-add {
            width: 100%;
            padding: 14px;
            background-color: var(--primary);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-add:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        /* Gaveta do Carrinho (Cart Drawer) */
        .cart-drawer {
            position: fixed;
            top: 0;
            right: -500px;
            width: 100%;
            max-width: 500px;
            height: 100%;
            background-color: var(--bg-card);
            box-shadow: -10px 0 40px rgba(0,0,0,0.15);
            z-index: 2000;
            display: flex;
            flex-direction: column;
            transition: right 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-left: 1px solid var(--border-color);
        }

        .cart-drawer.open {
            right: 0;
        }

        .cart-header {
            padding: 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-header h3 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-close-cart {
            width: 40px;
            height: 40px;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-close-cart:hover {
            background: #fee2e2;
            border-color: #ef4444;
            color: #ef4444;
            transform: rotate(90deg);
        }

        .cart-body {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .cart-item {
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .cart-item-title {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
            font-size: 1.05rem;
        }

        .cart-item-price {
            color: var(--primary);
            font-weight: 800;
            font-size: 1rem;
        }

        .cart-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-card);
            padding: 6px 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .qty-btn {
            background: transparent;
            border: none;
            font-size: 1.1rem;
            color: var(--text-muted);
            cursor: pointer;
            padding: 2px 6px;
        }

        .qty-btn:hover {
            color: var(--primary);
        }

        .cart-item-remove {
            color: #ef4444;
            background: #fee2e2;
            border: 1px solid rgba(239, 68, 68, 0.3);
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .cart-item-remove:hover {
            background: #ef4444;
            color: #ffffff;
        }

        .cart-footer {
            padding: 30px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-main);
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .summary-line.total {
            border-top: 2px solid var(--border-color);
            padding-top: 15px;
            margin-top: 15px;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .summary-line.total span:last-child {
            color: var(--primary);
        }

        .btn-checkout {
            width: 100%;
            padding: 18px;
            background-color: var(--primary);
            border: none;
            border-radius: 16px;
            color: #ffffff;
            font-weight: 800;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-checkout:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(16, 185, 129, 0.4);
        }

        /* Modais Principal (Checkout & Sucesso) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 650px;
            padding: 40px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-close {
            position: absolute;
            top: 25px;
            right: 25px;
            width: 40px;
            height: 40px;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.2rem;
        }

        .modal-close:hover {
            background: #fee2e2;
            border-color: #ef4444;
            color: #ef4444;
        }

        .form-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-title i {
            color: var(--primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .input-group {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }

        .input-group.full-width {
            grid-column: 1 / -1;
        }

        .input-group label {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .input-group input, .input-group select, .input-group textarea {
            padding: 14px 20px;
            background-color: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--text-main);
            outline: none;
            transition: all 0.3s;
        }

        .input-group input:focus, .input-group select:focus, .input-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
            background-color: var(--bg-card);
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 4000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: #1e293b;
            color: #ffffff;
            padding: 16px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: var(--shadow-xl);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideUp 0.3s ease;
        }

        .toast.success { border-left: 6px solid var(--primary); }
        .toast.error { border-left: 6px solid #ef4444; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Footer Externa */
        .store-footer {
            background-color: #0f172a;
            color: #94a3b8;
            padding: 60px 30px;
            margin-top: auto;
            border-top: 1px solid var(--border-color);
        }

        .footer-container {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 40px;
        }

        .footer-brand h3 {
            color: #ffffff;
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-brand i { color: var(--primary); }
    </style>
</head>
<body>

    <!-- Header Principal da Loja Externa -->
    <header class="store-header">
        <div class="header-container">
            <a href="index.php" class="store-logo">
                <i class="fas fa-bag-shopping"></i>
                <span>TSTORE <span style="color: var(--primary); font-weight: 500;">Storefront</span></span>
            </a>

            <form action="index.php" method="GET" class="header-search">
                <?php if ($cat_filter > 0): ?><input type="hidden" name="categoria" value="<?php echo $cat_filter; ?>"><?php endif; ?>
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="O que procura hoje?" value="<?php echo htmlspecialchars($search_filter); ?>">
            </form>

            <div class="header-actions">
                <?php if (isset($_SESSION['cliente_logado'])): ?>
                    <a href="conta.php" class="btn-cart-toggle" style="background: transparent; border-color: var(--border-color); color: var(--text-main);">
                        <i class="fas fa-user-circle"></i>
                        <span>Olá, <?php echo htmlspecialchars(explode(' ', $_SESSION['cliente_nome'])[0]); ?></span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-cart-toggle" style="background: transparent; border-color: var(--border-color); color: var(--text-main);">
                        <i class="fas fa-user"></i>
                        <span>Entrar</span>
                    </a>
                <?php endif; ?>

                <button type="button" class="btn-cart-toggle" onclick="toggleCartDrawer()">
                    <i class="fas fa-cart-shopping"></i>
                    <span>Carrinho</span>
                    <span class="cart-badge-count" id="headerCartCount"><?php echo $cart_count; ?></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="main-container">
        <!-- Hero Banner Premium -->
        <section class="hero-banner">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-bolt"></i> Nova Coleção & Entrega Expresso
                </div>
                <h1 class="hero-title">O Melhor da Tecnologia ao Seu Alcance.</h1>
                <p class="hero-subtitle">Explore a nossa montra digital oficial. Todos os pedidos são encaminhados instantaneamente para o nosso centro de distribuição e faturados com IVA incluído.</p>
                <div style="display: flex; gap: 15px;">
                    <a href="#produtos" class="btn-add" style="width: auto; padding: 16px 32px; font-size: 1.05rem;">Explorar Catálogo</a>
                </div>
            </div>
            <div class="hero-image-container">
                <i class="fas fa-boxes-stacked"></i>
            </div>
        </section>

        <!-- Filtros de Categoria -->
        <section class="category-filters" id="produtos">
            <a href="index.php#produtos" class="cat-pill <?php echo $cat_filter === 0 ? 'active' : ''; ?>">Todos os Produtos</a>
            <?php if ($categorias && $categorias->num_rows > 0): ?>
                <?php while ($cat = $categorias->fetch_assoc()): ?>
                    <a href="index.php?categoria=<?php echo $cat['id_categoria']; ?><?php echo !empty($search_filter) ? '&search='.urlencode($search_filter) : ''; ?>#produtos" class="cat-pill <?php echo $cat_filter == $cat['id_categoria'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['descricao']); ?>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </section>

        <!-- Grelha de Produtos -->
        <section class="products-grid">
            <?php if ($produtos && $produtos->num_rows > 0): ?>
                <?php while ($prod = $produtos->fetch_assoc()): ?>
                    <div class="product-card">
                        <?php if (!empty($prod['categoria_nome'])): ?>
                            <span class="product-badge"><?php echo htmlspecialchars($prod['categoria_nome']); ?></span>
                        <?php endif; ?>

                        <span class="product-stock"><?php echo $prod['quantidade']; ?> disponíveis</span>

                        <div class="product-image-box">
                            <?php if (!empty($prod['imagem']) && file_exists('../' . $prod['imagem'])): ?>
                                <img src="../<?php echo htmlspecialchars($prod['imagem']); ?>" alt="<?php echo htmlspecialchars($prod['descricao']); ?>">
                            <?php else: ?>
                                <i class="fas fa-box-open" style="font-size: 5rem; color: #cbd5e1;"></i>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h3 class="product-title"><?php echo htmlspecialchars($prod['descricao']); ?></h3>
                            <div class="product-price">
                                <?php echo number_format($prod['preco_unit'], 2, ',', '.'); ?> €
                                <span>c/ IVA</span>
                            </div>

                            <button type="button" class="btn-add" onclick="adicionarAoCarrinho(<?php echo $prod['id_produto']; ?>, 1)">
                                <i class="fas fa-cart-plus"></i> Adicionar ao Carrinho
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 80px 20px; background: #ffffff; border-radius: 24px; border: 2px dashed var(--border-color);">
                    <i class="fas fa-store-slash" style="font-size: 5rem; color: #cbd5e1; margin-bottom: 25px;"></i>
                    <h3 style="font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin-bottom: 12px;">Nenhum produto disponível</h3>
                    <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 500px; margin: 0 auto 25px auto;">Não encontrámos produtos para os filtros selecionados ou o stock esgotou temporariamente.</p>
                    <a href="index.php" class="btn-add" style="width: auto; display: inline-flex; padding: 14px 28px;">Ver Todos os Produtos</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Gaveta do Carrinho (Cart Drawer) -->
    <div class="cart-drawer" id="cartDrawer">
        <div class="cart-header">
            <h3><i class="fas fa-cart-shopping" style="color: var(--primary);"></i> Carrinho de Compras</h3>
            <div class="btn-close-cart" onclick="toggleCartDrawer()"><i class="fas fa-times"></i></div>
        </div>
        <div class="cart-body" id="cartItemsContainer">
            <!-- Preenchido via AJAX -->
        </div>
        <div class="cart-footer">
            <div class="summary-line">
                <span>Subtotal (Base):</span>
                <span id="cartSubtotal">0.00 €</span>
            </div>
            <div class="summary-line">
                <span>Total IVA:</span>
                <span id="cartIva">0.00 €</span>
            </div>
            <div class="summary-line" id="cartDiscountRow" style="display: none; color: #d97706;">
                <span>Desconto Fidelização (10%):</span>
                <span id="cartDiscount">-0.00 €</span>
            </div>
            <div class="summary-line total">
                <span>Total a Pagar:</span>
                <span id="cartTotal">0.00 €</span>
            </div>
            <button type="button" class="btn-checkout" onclick="abrirCheckout()">
                <i class="fas fa-lock"></i> Avançar para Checkout
            </button>
        </div>
    </div>

    <!-- Modal de Checkout -->
    <div id="checkoutModalOverlay" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-close" onclick="fecharCheckout()"><i class="fas fa-times"></i></div>
            <h2 class="form-title"><i class="fas fa-credit-card"></i> Finalizar Encomenda</h2>
            <form id="checkoutForm" onsubmit="submeterCheckout(event)">
                <div class="form-grid">
                    <div class="input-group full-width">
                        <label>Nome Completo (Conta)</label>
                        <input type="text" name="nome" id="cli_nome" readonly style="background-color: #f1f5f9; cursor: not-allowed;" value="<?php echo htmlspecialchars($_SESSION['cliente_nome'] ?? ''); ?>">
                    </div>
                    <div class="input-group">
                        <label>Email (Conta)</label>
                        <input type="email" name="email" id="cli_email" readonly style="background-color: #f1f5f9; cursor: not-allowed;" value="<?php echo htmlspecialchars($_SESSION['cliente_email'] ?? ''); ?>">
                    </div>
                    <div class="input-group">
                        <label>Telefone / Telemóvel *</label>
                        <input type="text" name="telefone" required placeholder="Ex: 912345678">
                    </div>
                    <div class="input-group full-width">
                        <label>Morada de Entrega *</label>
                        <input type="text" name="morada" required placeholder="Rua, Nº, Andar...">
                    </div>
                    <div class="input-group">
                        <label>Código Postal *</label>
                        <input type="text" name="cpostal" id="cli_cpostal" required placeholder="XXXX-XXX">
                    </div>
                    <div class="input-group">
                        <label>Localidade *</label>
                        <input type="text" name="localidade" id="cli_localidade" required placeholder="Localidade">
                    </div>
                    <div class="input-group full-width">
                        <label>Método de Pagamento *</label>
                        <select name="metodo_pagamento">
                            <option value="Multibanco">Multibanco (Entidade e Referência)</option>
                            <option value="MBWay">MBWay (Pagamento imediato)</option>
                            <option value="Cartão de Crédito">Cartão de Crédito / Débito</option>
                        </select>
                    </div>
                    <div class="input-group full-width">
                        <label>Notas / Instruções de Entrega</label>
                        <textarea name="observacoes" rows="2" placeholder="Ex: Deixar na portaria, campainha não funciona..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 10px; display: flex; justify-content: flex-end; gap: 15px;">
                    <button type="button" class="btn-add" onclick="fecharCheckout()" style="background: #e2e8f0; color: #64748b; width: auto; padding: 16px 28px;">Cancelar</button>
                    <button type="submit" class="btn-checkout" style="margin: 0; width: auto; padding: 16px 36px;"><i class="fas fa-check"></i> Confirmar Encomenda</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="sucessoModalOverlay" class="modal-overlay">
        <div class="modal-card" style="max-width: 550px; text-align: center; padding: 50px 40px;">
            <div style="width: 90px; height: 90px; background: var(--primary-light); border: 3px solid var(--primary); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 30px auto;">
                <i class="fas fa-check"></i>
            </div>
            <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 15px;">Encomenda Realizada com Sucesso!</h2>
            <p style="color: var(--text-muted); font-size: 1.15rem; margin-bottom: 30px; line-height: 1.6;">Obrigado pela sua compra. A sua encomenda com a referência <strong id="sucessoRef" style="color: var(--primary); font-size: 1.25rem;"></strong> foi transmitida diretamente para o nosso centro de logística.</p>
            
            <div style="background: var(--bg-main); border: 1px solid var(--border-color); padding: 25px; border-radius: var(--radius-lg); margin-bottom: 35px; text-align: left;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 1.1rem;">
                    <span style="color: var(--text-muted);">Total Pago:</span>
                    <strong id="sucessoTotalVal" style="color: var(--text-main); font-size: 1.25rem;"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.1rem;">
                    <span style="color: var(--text-muted);">Estado Logístico:</span>
                    <span style="background: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 0.9rem;">Pendente de Expedição</span>
                </div>
            </div>

            <button type="button" class="btn-checkout" onclick="fecharSucesso()" style="margin: 0; padding: 18px;">Continuar a Explorar a Loja</button>
        </div>
    </div>

    <!-- Container de Toasts -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Footer da Loja Externa -->
    <footer class="store-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <h3><i class="fas fa-bag-shopping"></i> TSTORE Storefront</h3>
                <p style="max-width: 400px; margin-bottom: 20px; line-height: 1.6;">A montra oficial de E-Commerce B2C integrada em tempo real com a plataforma de gestão de armazéns e inventário TSTORE.</p>
                <div style="display: flex; gap: 15px; font-size: 1.5rem; color: #ffffff;">
                    <i class="fab fa-facebook"></i>
                    <i class="fab fa-instagram"></i>
                    <i class="fab fa-twitter"></i>
                    <i class="fab fa-linkedin"></i>
                </div>
            </div>
            <div>
                <h4 style="color: #ffffff; font-size: 1.1rem; font-weight: 700; margin-bottom: 15px;">Métodos de Pagamento</h4>
                <div style="display: flex; gap: 15px; font-size: 2rem; color: #64748b;">
                    <i class="fas fa-credit-card"></i>
                    <i class="fas fa-building-columns"></i>
                    <i class="fas fa-mobile-screen-button"></i>
                </div>
            </div>
        </div>
        <div style="max-width: 1280px; margin: 40px auto 0 auto; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); text-align: center; font-size: 0.9rem;">
            &copy; <?php echo date('Y'); ?> TSTORE E-Commerce. Todos os direitos reservados.
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            atualizarCarrinhoDOM();
        });

        function showStoreToast(msg, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> <span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3500);
        }

        function toggleCartDrawer() {
            const drawer = document.getElementById('cartDrawer');
            drawer.classList.toggle('open');
            if (drawer.classList.contains('open')) {
                atualizarCarrinhoDOM();
            }
        }

        function adicionarAoCarrinho(idProduto, qtd) {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('id_produto', idProduto);
            formData.append('quantidade', qtd);

            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showStoreToast(data.message, 'success');
                        document.getElementById('headerCartCount').textContent = data.cart_count;
                        atualizarCarrinhoDOM();
                    } else {
                        showStoreToast(data.message, 'error');
                    }
                }).catch(e => showStoreToast("Erro ao adicionar ao carrinho.", 'error'));
        }

        function atualizarCarrinhoDOM() {
            const emailVal = document.getElementById('cli_email') ? document.getElementById('cli_email').value : '';
            const formData = new FormData();
            formData.append('action', 'get_cart');
            formData.append('email', emailVal);

            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('cartItemsContainer');
                        container.innerHTML = '';
                        document.getElementById('headerCartCount').textContent = data.cart_count;

                        if (data.carrinho.length === 0) {
                            container.innerHTML = `
                                <div style="text-align:center; padding: 60px 20px; color:#94a3b8;">
                                    <i class="fas fa-cart-shopping" style="font-size: 4rem; color:#cbd5e1; margin-bottom: 20px;"></i>
                                    <p style="font-size: 1.1rem;">O seu carrinho está vazio.</p>
                                </div>
                            `;
                            document.querySelector('.cart-footer .btn-checkout').disabled = true;
                            document.querySelector('.cart-footer .btn-checkout').style.opacity = '0.5';
                        } else {
                            document.querySelector('.cart-footer .btn-checkout').disabled = false;
                            document.querySelector('.cart-footer .btn-checkout').style.opacity = '1';

                            data.carrinho.forEach((item, index) => {
                                container.innerHTML += `
                                    <div class="cart-item">
                                        <div style="flex:1;">
                                            <div class="cart-item-title">${item.descricao}</div>
                                            <div class="cart-item-price">${parseFloat(item.preco_unitario).toFixed(2)} € <span style="font-size:0.8rem; color:#64748b;">(+${item.taxa_iva}% IVA)</span></div>
                                        </div>
                                        <div class="cart-controls">
                                            <button type="button" class="qty-btn" onclick="atualizarQtd(${index}, ${item.quantidade - 1})"><i class="fas fa-minus"></i></button>
                                            <span style="font-weight:800; min-width:24px; text-align:center; color:#0f172a;">${item.quantidade}</span>
                                            <button type="button" class="qty-btn" onclick="atualizarQtd(${index}, ${item.quantidade + 1})"><i class="fas fa-plus"></i></button>
                                        </div>
                                        <div class="cart-item-remove" onclick="removerDoCarrinho(${index})"><i class="fas fa-trash"></i></div>
                                    </div>
                                `;
                            });
                        }

                        document.getElementById('cartSubtotal').textContent = data.total_bruto + ' €';
                        document.getElementById('cartIva').textContent = data.total_iva + ' €';

                        if (data.tem_desconto && parseFloat(data.desconto_val) > 0) {
                            document.getElementById('cartDiscountRow').style.display = 'flex';
                            document.getElementById('cartDiscount').textContent = '-' + data.desconto_val + ' €';
                        } else {
                            document.getElementById('cartDiscountRow').style.display = 'none';
                        }

                        document.getElementById('cartTotal').textContent = data.total_final + ' €';
                    }
                });
        }

        function atualizarQtd(index, qtd) {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('index', index);
            formData.append('quantidade', qtd);

            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        atualizarCarrinhoDOM();
                    } else {
                        showStoreToast(data.message, 'error');
                    }
                });
        }

        function removerDoCarrinho(index) {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('index', index);

            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        atualizarCarrinhoDOM();
                        showStoreToast("Item removido do carrinho.", 'success');
                    }
                });
        }

        function abrirCheckout() {
            <?php if (!isset($_SESSION['cliente_logado'])): ?>
                window.location.href = 'login.php';
                return;
            <?php endif; ?>
            toggleCartDrawer();
            document.getElementById('checkoutModalOverlay').style.display = 'flex';
        }

        function fecharCheckout() {
            document.getElementById('checkoutModalOverlay').style.display = 'none';
        }

        function verificarDescontoEmail() {
            atualizarCarrinhoDOM();
        }

        function submeterCheckout(e) {
            e.preventDefault();
            const form = document.getElementById('checkoutForm');
            const formData = new FormData(form);
            formData.append('action', 'checkout');

            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        fecharCheckout();
                        document.getElementById('sucessoRef').textContent = data.num_encomenda;
                        document.getElementById('sucessoTotalVal').textContent = data.total + ' €';
                        document.getElementById('sucessoModalOverlay').style.display = 'flex';
                        form.reset();
                        atualizarCarrinhoDOM();
                    } else {
                        showStoreToast(data.message, 'error');
                    }
                }).catch(e => showStoreToast("Erro ao processar checkout.", 'error'));
        }

        function fecharSucesso() {
            document.getElementById('sucessoModalOverlay').style.display = 'none';
            window.location.href = 'index.php';
        }

        // Preencher localidade automaticamente
        document.getElementById('cli_cpostal').addEventListener('blur', function() {
            const cpostal = this.value.trim();
            if (cpostal.length >= 4) {
                fetch('../actions/get_localidade.php?cpostal=' + encodeURIComponent(cpostal))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('cli_localidade').value = data.localidade;
                        }
                    });
            }
        });
    </script>
</body>
</html>
