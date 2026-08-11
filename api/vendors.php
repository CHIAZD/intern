<?php

require_once "../config.php";

header("Content-Type: application/json");

$sql = "SELECT * FROM vendors";
$result = $conn->query($sql);

$vendors = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vendors[] = $row;
    }
}

echo json_encode($vendors);

?>