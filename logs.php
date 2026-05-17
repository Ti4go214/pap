<?php
/**
 * @file logs.php
 * @brief Página de visualização de logs de auditoria (apenas para admins).
 * @author Antigravity
 * @date 2026-03-13
 */

include 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

// Paginação simples para logs
$limit = 50;
$res_count = $conn->query("SELECT COUNT(*) as total FROM logs");
$total = $res_count->fetch_assoc()['total'];

$sql = "SELECT l.*, u.username 
        FROM logs l 
        LEFT JOIN users u ON l.id_user = u.id_user
        ORDER BY l.data_hora DESC 
        LIMIT $limit";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Auditoria do Sistema - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .nav-right { display: flex; align-items: center; gap: 15px; }

        .log-tag {
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: bold;
            background: rgba(188, 111, 241, 0.1);
            color: #bc6ff1;
        }
        .tag-danger { background: rgba(255, 75, 43, 0.1); color: #ff4b2b; }
        .tag-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .tag-warning { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>

        <main class="content-area">
            <header class="table-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-btn" onclick="location.href='gestao.php'" title="Voltar para Gestão" style="padding: 10px 15px;">
                        <i class="fas fa-arrow-left" style="margin: 0;"></i>
                    </button>
                    <div>
                        <h2>Logs de Auditoria</h2>
                        <p>Visualização das últimas <?php echo $limit; ?> ações críticas no sistema</p>
                    </div>
                </div>
            </header>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Utilizador</th>
                            <th>Ação</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $tagClass = "";
                                if (str_contains($row['acao'], 'ELIMINACAO')) $tagClass = "tag-danger";
                                if (str_contains($row['acao'], 'CRIACAO') || str_contains($row['acao'], 'REGISTO')) $tagClass = "tag-success";
                                if (str_contains($row['acao'], 'ALTERACAO')) $tagClass = "tag-warning";
                            ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo date("d/m/Y H:i:s", strtotime($row['data_hora'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['username'] ?? 'Sistema/Antigo (' . $row['id_user'] . ')'); ?></strong>
                                    </td>
                                    <td><span class="log-tag <?php echo $tagClass; ?>"><?php echo str_replace('_', ' ', $row['acao']); ?></span></td>
                                    <td style="font-size: 0.9rem; opacity: 0.8;"><?php echo htmlspecialchars($row['detalhes']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding: 50px;">Sem logs registados ainda.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</script>
</body>
</html>
