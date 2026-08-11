<?php

require_once "../config.php";

header("Content-Type: application/json");

$sql = "SELECT * FROM products";
$result = $conn->query($sql);

$products = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode($products);

?>