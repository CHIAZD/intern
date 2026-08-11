<?php

require_once "../config.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Only POST request is allowed"
    ]);

    exit;
}


$data = json_decode(
    file_get_contents("php://input"),
    true
);


if (!$data) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request data"
    ]);

    exit;
}


$poId = $data["PO_ID"] ?? "";
$vendorId = $data["Vendor_ID"] ?? "";
$poDate = $data["PO_Date"] ?? "";
$status = $data["Status"] ?? "DRAFT";
$items = $data["Items"] ?? [];


if (
    empty($poId) ||
    empty($vendorId) ||
    empty($poDate) ||
    empty($items)
) {

    echo json_encode([
        "success" => false,
        "message" => "Missing required information"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Calculate total
|--------------------------------------------------------------------------
*/

$totalAmount = 0;

foreach ($items as $item) {

    $quantity =
        (float)($item["Quantity"] ?? 0);

    $unitPrice =
        (float)($item["Unit_Price"] ?? 0);

    $subtotal =
        $quantity * $unitPrice;

    $totalAmount += $subtotal;
}


/*
|--------------------------------------------------------------------------
| Start Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {

    /*
    |--------------------------------------------------------------------------
    | Insert PO
    |--------------------------------------------------------------------------
    */

    $sql = "
        INSERT INTO purchase_orders
        (
            PO_ID,
            Vendor_ID,
            PO_Date,
            Total_Amount,
            Status
        )
        VALUES (?, ?, ?, ?, ?)
    ";


    $stmt =
        $conn->prepare($sql);


    $stmt->bind_param(
        "sssds",
        $poId,
        $vendorId,
        $poDate,
        $totalAmount,
        $status
    );


    $stmt->execute();


    /*
    |--------------------------------------------------------------------------
    | Insert PO Items
    |--------------------------------------------------------------------------
    */

    $itemSql = "
        INSERT INTO purchase_order_items
        (
            PO_ID,
            Product_ID,
            Quantity,
            UOM,
            Unit_Price,
            Sub_Total,
            Stock_Qty
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";


    $itemStmt =
        $conn->prepare($itemSql);


    foreach ($items as $item) {

        $productId =
            $item["Product_ID"];

        $quantity =
            (float)$item["Quantity"];

        $uom =
            $item["UOM"];

        $unitPrice =
            (float)$item["Unit_Price"];

        $subtotal =
            $quantity * $unitPrice;

        $stockQty =
            (float)$item["Stock_Qty"];


        $itemStmt->bind_param(
            "ssdsddd",
            $poId,
            $productId,
            $quantity,
            $uom,
            $unitPrice,
            $subtotal,
            $stockQty
        );


        $itemStmt->execute();

    }


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    echo json_encode([
        "success" => true,
        "message" => "Purchase Order saved successfully",
        "PO_ID" => $poId
    ]);


} catch (Exception $e) {

    $conn->rollback();


    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}

?>