<?php

require_once "../config.php";

header("Content-Type: application/json");

$sql = "
    SELECT
        Vendor_ID,
        Vendor_CompanyName
    FROM vendors
    ORDER BY Vendor_CompanyName
";

$result = $conn->query($sql);

if (!$result) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to load vendors: " . $conn->error
    ]);

    exit;
}

$vendors = [];

while ($row = $result->fetch_assoc()) {

    $vendors[] = $row;

}

echo json_encode([
    "success" => true,
    "data" => $vendors
]);

?>