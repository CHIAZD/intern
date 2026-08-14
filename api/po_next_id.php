<?php

session_start();

require_once "../config.php";

header("Content-Type: application/json");


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_level"])
) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please login first"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Current Year / Month
|--------------------------------------------------------------------------
*/

$currentYear = date("y");
$currentMonth = date("m");

$prefix =
    "PO-MYG" .
    $currentYear .
    "-" .
    $currentMonth .
    "-";


/*
|--------------------------------------------------------------------------
| Get Latest PO For Current Month
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT PO_ID
    FROM purchase_orders
    WHERE PO_ID LIKE ?
    ORDER BY PO_ID DESC
    LIMIT 1
");

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare query: " . $conn->error
    ]);

    exit;
}


$pattern = $prefix . "%";


$stmt->bind_param(
    "s",
    $pattern
);


$stmt->execute();


$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Generate Number
|--------------------------------------------------------------------------
*/

if ($result->num_rows === 0) {

    $nextNumber = 1;

} else {

    $row = $result->fetch_assoc();

    $lastPOId = $row["PO_ID"];

    $lastNumber = intval(
        substr($lastPOId, -3)
    );

    $nextNumber = $lastNumber + 1;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Maximum 999
|--------------------------------------------------------------------------
*/

if ($nextNumber > 999) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Monthly PO limit reached. Maximum 999 POs are allowed."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Final PO ID
|--------------------------------------------------------------------------
*/

$nextPOId =
    $prefix .
    str_pad(
        $nextNumber,
        3,
        "0",
        STR_PAD_LEFT
    );


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "po_id" => $nextPOId
]);

?>