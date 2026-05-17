<?php
include 'includes/db_connect.php';
$res = $conn->query("SELECT * FROM config");
while($row = $res->fetch_assoc()) {
    echo $row['param_key'] . " = " . $row['param_value'] . "\n";
}
?>
