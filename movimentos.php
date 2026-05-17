<?php
include 'includes/db_connect.php';
include 'includes/ListManager.php';
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

// Paginação personalizada para UNION requer contagem manual ou abordagem mais simples.
// Por agora, vamos buscar os últimos 50 movimentos mistos.
$sql = "
(SELECT n_cab as id_doc, cliente as entidade, data, 'ENTRADA' as tipo 
 FROM ent_cab)
UNION
(SELECT n_cab as id_doc, cliente as entidade, data, 'SAIDA' as tipo 
 FROM sai_cab)
ORDER BY CAST(data AS DATETIME) DESC
LIMIT 50
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Movimentos de Stock - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }
    </style>
</script>
</head>

<body>
    <div class="background-overlay"></div>

    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>

        <main class="content-area">
            <header class="table-header">
                <div>
                    <h2>Histórico de Movimentos</h2>
                    <p>Últimos registos de entrada e saída</p>
                </div>
                <div class="header-actions" style="display: flex; gap: 15px;">
                    <button class="nav-btn" onclick="location.href='actions/export_actions.php?type=movimentos'" 
                            style="border-color: rgba(16, 185, 129, 0.4); color: #10b981; background: rgba(16, 185, 129, 0.05);">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </button>
                    <?php if (($_SESSION['role'] ?? 1) == 0 || ($_SESSION['role'] ?? 1) == 2): ?>
                        <button class="nav-btn active" onclick="location.href='registar_movimento.php'">
                            <i class="fas fa-plus-circle"></i> Registar Movimento
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID Doc</th>
                            <th>Tipo</th>
                            <th>Entidade (Cliente/Fornecedor)</th>
                            <th>Data</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $tipoColor = ($row['tipo'] == 'ENTRADA') ? '#10b981' : '#ff4b2b';
                                $tipoIcon = ($row['tipo'] == 'ENTRADA') ? 'fa-arrow-down' : 'fa-arrow-up';
                                ?>
                                <tr>
                                    <td>#
                                        <?php echo htmlspecialchars($row["id_doc"]); ?>
                                    </td>
                                    <td>
                                        <span style="color: <?php echo $tipoColor; ?>; font-weight: bold;">
                                            <i class="fas <?php echo $tipoIcon; ?>"></i>
                                            <?php echo $row["tipo"]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row["entidade"]); ?>
                                    </td>
                                    <td>
                                        <?php echo date("d/m/Y H:i", strtotime($row["data"])); ?>
                                    </td>
                                    <td>
                                        <!-- Ações de movimento -->
                                        <div style="display: flex; gap: 8px;">
                                            <button class="edit-btn" title="Ver Linhas"
                                                onclick="location.href='ver_movimento.php?id=<?php echo $row['id_doc']; ?>&tipo=<?php echo $row['tipo']; ?>'">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="edit-btn" title="Fatura PDF"
                                                onclick="window.open('actions/gerar_fatura.php?tipo=<?php echo strtolower($row['tipo']); ?>&id=<?php echo $row['id_doc']; ?>', '_blank')"
                                                style="background: rgba(188, 111, 241, 0.1); border-color: #bc6ff1; color: #bc6ff1;">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; padding: 50px; opacity: 0.5;'>Sem movimentos registados.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>