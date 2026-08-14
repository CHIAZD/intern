<?php

require_once "../config.php";

header("Content-Type: application/json");


/*
|--------------------------------------------------------------------------
| Get PO ID
|--------------------------------------------------------------------------
*/

$poId = $_GET["po_id"] ?? "";


if ($poId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "PO ID is required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get PO Header
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        po.PO_ID,
        po.Vendor_ID,
        v.Vendor_CompanyName,
        po.PO_Date,
        po.Total_Amount,
        po.Total_Qty,
        po.Status,
        po.Created_At,
        po.Created_By,
        po.Photo_Path,
        po.Prepared_Date,
        po.Ref_No,
        po.Description,
        po.Currency
    FROM purchase_orders po

    LEFT JOIN vendors v
        ON po.Vendor_ID = v.Vendor_ID

    WHERE po.PO_ID = ?
");


$stmt->bind_param(
    "s",
    $poId
);


$stmt->execute();


$result =
    $stmt->get_result();


$po =
    $result->fetch_assoc();


$stmt->close();


if (!$po) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "PO not found"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get PO Items
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        PO_Item_ID,
        PO_ID,
        Product_ID,
        Item_Name,
        Quantity,
        UOM,
        Unit_Price,
        Sub_Total,
        Stock_Qty
    FROM purchase_order_items
    WHERE PO_ID = ?
    ORDER BY PO_Item_ID ASC
");


$stmt->bind_param(
    "s",
    $poId
);


$stmt->execute();


$result =
    $stmt->get_result();


$items = [];


while ($row = $result->fetch_assoc()) {

    $items[] = $row;

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Return
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "data" => [

        "header" => $po,

        "items" => $items

    ]

]);

?>