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

if (!isset($_GET['id']) || !isset($_GET['tipo'])) {
    header("Location: movimentos.php");
    exit();
}

$id = intval($_GET['id']);
$tipo = $_GET['tipo'];

// Obter cabeçalho
if ($tipo == 'ENTRADA') {
    $stmt = $conn->prepare("SELECT n_cab as id_doc, cliente as entidade, data FROM ent_cab WHERE n_cab = ?");
} else {
    $stmt = $conn->prepare("SELECT n_cab as id_doc, cliente as entidade, data FROM sai_cab WHERE n_cab = ?");
}

if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $header = $res->fetch_assoc();
} else {
    $header = false;
}

if (!$header) {
    die("Movimento não encontrado ou erro na base de dados.");
}

// Obter linhas
$stmt_lines = $conn->prepare("
    SELECT l.*, p.descricao as prod_nome 
    FROM linhas l 
    LEFT JOIN produtos p ON l.id_produto = p.id_produto 
    WHERE l.id = ?
    ORDER BY l.n_linha ASC
");
$stmt_lines->bind_param("i", $id);
$stmt_lines->execute();
$lines_res = $stmt_lines->get_result();
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Detalhes do Movimento - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }

        .details-panel {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(188, 111, 241, 0.1);
            margin-bottom: 20px;
        }

        .header-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-box {
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 10px;
        }

        .info-box span {
            display: block;
            color: #aaa;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .info-box strong {
            color: #fff;
            font-size: 1.1rem;
        }
    </style>
</head>

<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>

        <main class="content-area">
            <header class="table-header">
                <div>
                    <h2>Detalhes do Movimento #
                        <?php echo $header['id_doc']; ?>
                    </h2>
                    <p>Visualização das linhas e informações gerais</p>
                </div>
            </header>

            <div class="details-panel">
                <div class="header-info">
                    <div class="info-box">
                        <span>Tipo</span>
                        <strong style="color: <?php echo $tipo == 'ENTRADA' ? '#10b981' : '#ff4b2b'; ?>;">
                            <i class="fas <?php echo $tipo == 'ENTRADA' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                            <?php echo htmlspecialchars($tipo); ?>
                        </strong>
                    </div>
                    <div class="info-box">
                        <span>Entidade</span>
                        <strong>
                            <?php echo htmlspecialchars($header['entidade']); ?>
                        </strong>
                    </div>
                    <div class="info-box">
                        <span>Data</span>
                        <strong>
                            <?php echo date("d/m/Y H:i", strtotime($header['data'])); ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Linha</th>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th>Preço Unitário</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($lines_res && $lines_res->num_rows > 0) {
                            $total_doc = 0;
                            while ($line = $lines_res->fetch_assoc()) {
                                $total_linha = $line['quantidade'] * $line['preço'];
                                $total_doc += $total_linha;
                                ?>
                                <tr>
                                    <td>
                                        <?php echo $line['n_linha']; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($line['prod_nome'] ? $line['prod_nome'] : "ID " . $line['id_produto']); ?>
                                    </td>
                                    <td>
                                        <?php echo $line['quantidade']; ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($line['preço'], 2, ',', '.'); ?> <?php echo $currency; ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($total_linha, 2, ',', '.'); ?> <?php echo $currency; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                            <tr style="background: rgba(188, 111, 241, 0.1);">
                                <td colspan="4" style="text-align: right; font-weight: bold;">TOTAL DO DOCUMENTO:</td>
                                <td style="font-weight: bold; color: #bc6ff1;">
                                    <?php echo number_format($total_doc, 2, ',', '.'); ?> <?php echo $currency; ?>
                                </td>
                            </tr>
                            <?php
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; padding: 20px;'>Nenhuma linha encontrada.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</script>
</body>
</html>