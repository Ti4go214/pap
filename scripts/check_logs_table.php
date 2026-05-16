<?php
include __DIR__ . '/../includes/db_connect.php';
$result = $conn->query("SHOW TABLES LIKE 'logs'");
if ($result->num_rows == 0) {
    echo "TABLE_MISSING";
    // Criar tabela se não existir
    $sql = "CREATE TABLE logs (
        id_log INT AUTO_INCREMENT PRIMARY KEY,
        id_user VARCHAR(30) NOT NULL,
        acao VARCHAR(50) NOT NULL,
        detalhes TEXT,
        data_hora DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql)) {
        echo "|TABLE_CREATED";
    } else {
        echo "|CREATE_FAILED: " . $conn->error;
    }
} else {
    echo "TABLE_EXISTS";
    $res = $conn->query("DESCRIBE logs");
    while($row = $res->fetch_assoc()) {
        echo "|COLUMN:" . $row['Field'];
    }
}
?>
