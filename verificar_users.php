<?php
include 'includes/db_connect.php';

echo "<h2>Estrutura da tabela users:</h2>";
$result = $conn->query("SHOW COLUMNS FROM users");
while ($row = $result->fetch_assoc()) {
    echo "<p>" . $row['Field'] . " - " . $row['Type'] . "</p>";
}

echo "<h2>Dados dos utilizadores:</h2>";
$result = $conn->query("SELECT * FROM users LIMIT 1");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p>Sem utilizadores na base de dados.</p>";
}
?>
