<?php
include __DIR__ . '/../includes/db_connect.php';

$res1 = $conn->query("DESCRIBE ent_cab");
echo "ent_cab schema:\n";
while ($row = $res1->fetch_assoc()) {
    print_r($row);
}

$res2 = $conn->query("DESCRIBE sai_cab");
echo "\nsai_cab schema:\n";
while ($row = $res2->fetch_assoc()) {
    print_r($row);
}
?>