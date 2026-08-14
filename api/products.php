<?php

require_once "../config.php";

header("Content-Type: application/json");

$sql = "
    SELECT
        Product_ID,
        Product_Description
    FROM products
    ORDER BY Product_ID
";

$result = $conn->query($sql);

if (!$result) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to load products: " . $conn->error
    ]);

    exit;
}

$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $products
]);

?>