<?php
/**
 * @file get_localidade.php
 * @brief API para buscar localidade por código postal
 * @author Antigravity
 * @date 2026-05-05
 */

header('Content-Type: application/json');

include __DIR__ . '/../includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cpostal = $_GET['cpostal'] ?? '';

if (empty($cpostal)) {
    echo json_encode(['success' => false, 'message' => 'Código postal não fornecido']);
    exit();
}

// Buscar localidade na tabela postais
$stmt = $conn->prepare("SELECT localidadepostal, concelho FROM postais WHERE id_postal = ? LIMIT 1");
$stmt->bind_param("s", $cpostal);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    // Retornar localidadepostal como localidade
    echo json_encode([
        'success' => true,
        'localidade' => $row['localidadepostal'],
        'concelho' => $row['concelho']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Código postal não encontrado']);
}
?>
