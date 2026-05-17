<?php
include '../includes/db_connect.php';
echo "--- ENCOMENDAS ---\n";
$res = $conn->query("SHOW COLUMNS FROM encomendas");
while($row = $res->fetch_assoc()) { echo $row['Field'] . " - " . $row['Type'] . "\n"; }
echo "\n--- ENCOMENDAS_LINHAS ---\n";
$res = $conn->query("SHOW COLUMNS FROM encomendas_linhas");
while($row = $res->fetch_assoc()) { echo $row['Field'] . " - " . $row['Type'] . "\n"; }
?>
