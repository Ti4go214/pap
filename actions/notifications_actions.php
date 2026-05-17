<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../includes/db_connect.php';

// Funções de notificação com tratamento de erro básico para evitar falhas se a tabela não existir ainda
function checkStockAlerts() {
    global $conn, $stock_limit;
    $user_id = $_SESSION['id_user'] ?? null;
    if (!$user_id) return;

    try {
        // Buscar produtos abaixo do limite que ainda não têm notificação pendente 
        // ou que não tenham sido arquivadas com a mesma quantidade atual
        $sql = "SELECT p.* FROM produtos p 
                WHERE p.quantidade <= $stock_limit 
                AND NOT EXISTS (
                    SELECT 1 FROM notificacoes n 
                    WHERE n.id_user = '$user_id'
                    AND n.tipo = 'STOCK_BAIXO' 
                    AND n.mensagem LIKE CONCAT('%', p.descricao, '%') 
                    AND (n.lida = 0 OR n.mensagem LIKE CONCAT('%(', p.quantidade, ').%'))
                )";
        
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($p = $res->fetch_assoc()) {
                $msg = "Aviso: O produto '" . $p['descricao'] . "' atingiu o stock crítico (" . $p['quantidade'] . ").";
                $link = "stock.php?edit_id=" . $p['id_produto']; // Link direto para edição
                $stmt = $conn->prepare("INSERT INTO notificacoes (id_user, tipo, mensagem, link) VALUES (?, 'STOCK_BAIXO', ?, ?)");
                $stmt->bind_param("sss", $user_id, $msg, $link);
                $stmt->execute();
            }
        }
    } catch (Exception $e) {
        // Silencioso se houver erro de BD
    }
}

function getUnreadNotifications() {
    global $conn;
    $user_id = $_SESSION['id_user'] ?? null;
    if (!$user_id) return false;
    try {
        return $conn->query("SELECT * FROM notificacoes WHERE id_user = '$user_id' AND lida = 0 ORDER BY data_criacao DESC LIMIT 5");
    } catch(Exception $e) { return false; }
}

function getUnreadCount() {
    global $conn;
    $user_id = $_SESSION['id_user'] ?? null;
    if (!$user_id) return 0;
    try {
        $res = $conn->query("SELECT COUNT(*) as total FROM notificacoes WHERE id_user = '$user_id' AND lida = 0");
        return $res->fetch_assoc()['total'] ?? 0;
    } catch(Exception $e) { return 0; }
}

// Se for chamado via AJAX para marcar como lida
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $user_id = $_SESSION['id_user'] ?? null;
    
    if ($user_id) {
        $success = $conn->query("UPDATE notificacoes SET lida = 1 WHERE id_notificacao = $id AND id_user = '$user_id'");
    } else {
        $success = false;
    }
    
    // Suporte para AJAX
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'id' => $id]);
        exit();
    }
    
    header("Location: ../" . ($_GET['redirect'] ?? 'index.php'));
    exit();
}
?>
