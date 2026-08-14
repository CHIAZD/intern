<?php

session_start();

require_once "../config.php";

header("Content-Type: application/json");


/*
|--------------------------------------------------------------------------
| ONLY POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST method is allowed"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET JSON
|--------------------------------------------------------------------------
*/

$input = json_decode(
    file_get_contents("php://input"),
    true
);


if (!$input) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request data"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET DATA
|--------------------------------------------------------------------------
*/

$grnId = trim(
    $input["grn_id"] ?? ""
);

$grnDate =
    $input["grn_date"] ?? "";

$items =
    $input["items"] ?? [];


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($grnId === "") {

    echo json_encode([
        "success" => false,
        "message" => "GRN ID is missing."
    ]);

    exit;
}


if ($grnDate === "") {

    echo json_encode([
        "success" => false,
        "message" => "GRN Date is missing."
    ]);

    exit;
}


if (
    !is_array($items) ||
    count($items) === 0
) {

    echo json_encode([
        "success" => false,
        "message" => "No GRN items found."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CALCULATE TOTAL
|--------------------------------------------------------------------------
*/

$totalAmount = 0;

foreach ($items as $item) {

    $subTotal =
        (float)($item["sub_total"] ?? 0);

    $totalAmount += $subTotal;
}


/*
|--------------------------------------------------------------------------
| START TRANSACTION
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {

    /*
    |--------------------------------------------------------------------------
    | INSERT GRN HEADER
    |--------------------------------------------------------------------------
    */

    $sql = "
        INSERT INTO grns
        (
            GRN_ID,
            GRN_Date,
            Total_Amount,
            Status
        )
        VALUES (?, ?, ?, 'DRAFT')
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Failed to prepare GRN insert: "
            . $conn->error
        );

    }


    $stmt->bind_param(
        "ssd",
        $grnId,
        $grnDate,
        $totalAmount
    );


    $stmt->execute();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | INSERT GRN ITEMS
    |--------------------------------------------------------------------------
    */

    $itemSql = "
        INSERT INTO grn_items
        (
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
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";


    $itemStmt =
        $conn->prepare($itemSql);


    if (!$itemStmt) {

        throw new Exception(
            "Failed to prepare GRN item insert: "
            . $conn->error
        );

    }


    foreach ($items as $item) {

        $poId =
            $item["po_id"] ?? "";

        $poItemId =
            (int)($item["po_item_id"] ?? 0);

        $productId =
            $item["product_id"] ?? "";

        $itemName =
            $item["item_name"] ?? "";

        $quantity =
            (int)($item["quantity"] ?? 0);

        $uom =
            $item["uom"] ?? "PCS";

        $qtyPerCarton =
            (int)($item["qty_per_carton"] ?? 0);

        $totalQty =
            (int)($item["total_qty"] ?? 0);

        $unitPrice =
            (float)($item["unit_price_myr"] ?? 0);

        $subTotal =
            (float)($item["sub_total"] ?? 0);


        $itemStmt->bind_param(
            "ssissisiddd",
            $grnId,
            $poId,
            $poItemId,
            $productId,
            $itemName,
            $quantity,
            $uom,
            $qtyPerCarton,
            $totalQty,
            $unitPrice,
            $subTotal
        );


        $itemStmt->execute();

    }


    $itemStmt->close();


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    echo json_encode([

        "success" => true,

        "message" =>
            "GRN saved successfully.",

        "grn_id" =>
            $grnId,

        "total_amount" =>
            number_format(
                $totalAmount,
                2,
                ".",
                ""
            )

    ]);


}
catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    echo json_encode([

        "success" => false,

        "message" =>
            $e->getMessage()

    ]);

}

?>