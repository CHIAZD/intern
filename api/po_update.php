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
| Level 2 Only
|--------------------------------------------------------------------------
*/

$userId =
    $_SESSION["user_id"];

$userLevel =
    (int)$_SESSION["user_level"];


if ($userLevel !== 2) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Permission denied. Level 2 is required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Only POST
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
| Get JSON
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
| Basic Data
|--------------------------------------------------------------------------
*/

$poId =
    trim($input["po_id"] ?? "");

$vendorId =
    trim($input["vendor_id"] ?? "");

$refNo =
    trim($input["ref_no"] ?? "");

$description =
    trim($input["description"] ?? "");

$currency =
    trim($input["currency"] ?? "RM");

$preparedDate =
    $input["prepared_date"] ?? null;

$items =
    $input["items"] ?? [];


/*
|--------------------------------------------------------------------------
| Validate
|--------------------------------------------------------------------------
*/

if ($poId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "PO ID is required"
    ]);

    exit;
}


if ($vendorId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Vendor is required"
    ]);

    exit;
}


if (
    !is_array($items) ||
    count($items) === 0
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "At least one PO item is required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check PO Exists
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM purchase_orders
    WHERE PO_ID = ?
");


$stmt->bind_param(
    "s",
    $poId
);


$stmt->execute();


$result =
    $stmt->get_result();


$oldPO =
    $result->fetch_assoc();


$stmt->close();


if (!$oldPO) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "PO not found"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Calculate Totals
|--------------------------------------------------------------------------
*/

$totalQty = 0;

$totalAmount = 0;


foreach ($items as $index => $item) {

    $productId =
        trim($item["product_id"] ?? "");

    $quantity =
        $item["quantity"] ?? 0;

    $uom =
        trim($item["uom"] ?? "PCS");

    $unitPrice =
        $item["unit_price"] ?? 0;

    $stockQty =
        $item["stock_qty"] ?? 0;


    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    */

    if ($productId === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" =>
                "Product is required for Item " .
                ($index + 1)
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Quantity
    |--------------------------------------------------------------------------
    */

    if (
        !is_numeric($quantity) ||
        intval($quantity) <= 0 ||
        intval($quantity) != $quantity
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" =>
                "Quantity must be a positive integer for Item " .
                ($index + 1)
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Unit Price
    |--------------------------------------------------------------------------
    */

    if (
        !is_numeric($unitPrice) ||
        floatval($unitPrice) < 0
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid Unit Price for Item " .
                ($index + 1)
        ]);

        exit;
    }


    $quantity =
        intval($quantity);

    $unitPrice =
        round(
            floatval($unitPrice),
            2
        );

    $stockQty =
        intval($stockQty);


    $subTotal =
        round(
            $quantity * $unitPrice,
            2
        );


    $totalQty +=
        $quantity;

    $totalAmount +=
        $subTotal;
}


/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {


    /*
    |--------------------------------------------------------------------------
    | Update PO Header
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE purchase_orders

        SET
            Vendor_ID = ?,
            Total_Amount = ?,
            Total_Qty = ?,
            Prepared_Date = ?,
            Ref_No = ?,
            Description = ?,
            Currency = ?

        WHERE PO_ID = ?
    ");


    $stmt->bind_param(
        "sdisssss",
        $vendorId,
        $totalAmount,
        $totalQty,
        $preparedDate,
        $refNo,
        $description,
        $currency,
        $poId
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to update PO header: " .
            $stmt->error
        );
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete Existing Items
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM purchase_order_items
        WHERE PO_ID = ?
    ");


    $stmt->bind_param(
        "s",
        $poId
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to remove old PO items: " .
            $stmt->error
        );
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Insert New Items
    |--------------------------------------------------------------------------
    */

$stmt = $conn->prepare("
    INSERT INTO purchase_order_items
    (
        PO_ID,
        Product_ID,
        Item_Name,
        Quantity,
        UOM,
        Unit_Price,
        Sub_Total,
        Stock_Qty
    )

    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");


    foreach ($items as $item) {

        $productId =
            trim($item["product_id"]);
        
        $itemName =
            trim($item["item_name"] ?? "");

        $quantity =
            intval($item["quantity"]);

        $uom =
            trim(
                $item["uom"] ?? "PCS"
            );

        $unitPrice =
            round(
                floatval($item["unit_price"]),
                2
            );

        $subTotal =
            round(
                $quantity * $unitPrice,
                2
            );

        $stockQty =
            intval(
                $item["stock_qty"] ?? 0
            );


$stmt->bind_param(
    "sssisdii",
    $poId,
    $productId,
    $itemName,
    $quantity,
    $uom,
    $unitPrice,
    $subTotal,
    $stockQty
);


        if (!$stmt->execute()) {

            throw new Exception(
                "Failed to insert PO item: " .
                $stmt->error
            );
        }
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    $action =
        "UPDATE";

    $module =
        "PO";


    $oldValue =
        json_encode([
            "vendor_id" =>
                $oldPO["Vendor_ID"],

            "total_amount" =>
                $oldPO["Total_Amount"],

            "total_qty" =>
                $oldPO["Total_Qty"],

            "ref_no" =>
                $oldPO["Ref_No"],

            "description" =>
                $oldPO["Description"],

            "currency" =>
                $oldPO["Currency"],

            "prepared_date" =>
                $oldPO["Prepared_Date"]
        ]);


    $newValue =
        json_encode([
            "vendor_id" =>
                $vendorId,

            "total_amount" =>
                $totalAmount,

            "total_qty" =>
                $totalQty,

            "ref_no" =>
                $refNo,

            "description" =>
                $description,

            "currency" =>
                $currency,

            "prepared_date" =>
                $preparedDate
        ]);


    $stmt = $conn->prepare("
        INSERT INTO audit_logs
        (
            User_ID,
            Action,
            Module,
            Record_ID,
            Old_Value,
            New_Value
        )

        VALUES (?, ?, ?, ?, ?, ?)
    ");


    $stmt->bind_param(
        "ssssss",
        $userId,
        $action,
        $module,
        $poId,
        $oldValue,
        $newValue
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to create audit log: " .
            $stmt->error
        );
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    echo json_encode([

        "success" => true,

        "message" =>
            "PO updated successfully",

        "data" => [

            "po_id" =>
                $poId,

            "total_qty" =>
                $totalQty,

            "total_amount" =>
                number_format(
                    $totalAmount,
                    2,
                    ".",
                    ""
                )

        ]

    ]);


} catch (Exception $e) {


    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    http_response_code(500);


    echo json_encode([

        "success" => false,

        "message" =>
            $e->getMessage()

    ]);
}

?>