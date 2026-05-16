<?php
include __DIR__ . '/../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

$action = $_POST['action'] ?? '';
$ids_json = $_POST['ids'] ?? '[]';
$ids = json_decode($ids_json, true);

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum ID selecionado.']);
    exit();
}

if ($action === 'bulk_delete') {
    // Escapar IDs para segurança
    $ids_placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    
    $stmt = $conn->prepare("DELETE FROM produtos WHERE id_produto IN ($ids_placeholders)");
    $stmt->bind_param($types, ...$ids);
    
    if ($stmt->execute()) {
        registarLog($_SESSION['id_user'], "ELIMINACAO_MASSA", "Eliminou " . count($ids) . " produtos.");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao eliminar: ' . $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
}
