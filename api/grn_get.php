<?php

session_start();

require_once "../config.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only GET request is allowed"
    ]);

    exit;
}


$grnId = trim($_GET["grn_id"] ?? "");


if ($grnId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "GRN ID is required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get GRN Header
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        GRN_ID,
        GRN_Date,
        Total_Amount,
        Status,
        Created_At,
        Created_By,
        Photo_Path,
        ETD_Date,
        ETA_Date,
        Exchange_Rate,
        Entry,
        Cost_Per_Entry,
        Carton_Number
    FROM grns
    WHERE GRN_ID = ?
    LIMIT 1
");


$stmt->bind_param(
    "s",
    $grnId
);


$stmt->execute();


$result = $stmt->get_result();


$grn = $result->fetch_assoc();


$stmt->close();


if (!$grn) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "GRN not found"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get GRN Items
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        GRN_ID,
        PO_ID,
        PO_Item_ID,
        Product_ID,
        Item_Name,
        Quantity,
        UOM,
        Qty_Per_Carton,
        Total_Qty,
        Unit_Price_MYR,
        Sub_Total
    FROM grn_items
    WHERE GRN_ID = ?
    ORDER BY PO_Item_ID ASC
");


$stmt->bind_param(
    "s",
    $grnId
);


$stmt->execute();


$result = $stmt->get_result();


$items = [];


while ($row = $result->fetch_assoc()) {

    $items[] = $row;

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "grn" => $grn,

    "items" => $items

]);

?>