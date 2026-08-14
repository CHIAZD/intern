<?php

require_once "../config.php";

header("Content-Type: application/json");

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

$input = file_get_contents("php://input");

$data = json_decode($input, true);

if (!is_array($data)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON data"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Header Data
|--------------------------------------------------------------------------
*/

$vendorId = $data["vendor_id"] ?? "";
$refNo = $data["ref_no"] ?? "";
$description = $data["description"] ?? "";
$currency = $data["currency"] ?? "RM";
$preparedDate = $data["prepared_date"] ?? null;
$createdBy = $data["created_by"] ?? null;
$items = $data["items"] ?? [];


/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if ($vendorId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Vendor is required"
    ]);

    exit;
}

if (!is_array($items) || count($items) === 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "At least one item is required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Vendor
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT Vendor_ID
    FROM vendors
    WHERE Vendor_ID = ?
");

$stmt->bind_param("s", $vendorId);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Vendor does not exist"
    ]);

    exit;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Validate Items
|--------------------------------------------------------------------------
*/

$totalQty = 0;
$totalAmount = 0;

$validatedItems = [];


foreach ($items as $item) {

    $productId = $item["product_id"] ?? "";
    $quantity = $item["quantity"] ?? 0;
    $uom = $item["uom"] ?? "PCS";
    $unitPrice = $item["unit_price"] ?? 0;
    $stockQty = $item["stock_qty"] ?? 0;


    /*
     * Product ID
     */

    if ($productId === "") {

        throw new Exception("Product ID is required");
    }


    /*
     * Quantity
     */

    if (!is_numeric($quantity) || intval($quantity) <= 0) {

        throw new Exception(
            "Quantity must be a positive integer"
        );
    }

    $quantity = intval($quantity);


    /*
     * Unit Price
     */

    if (!is_numeric($unitPrice) || floatval($unitPrice) < 0) {

        throw new Exception(
            "Invalid Unit Price"
        );
    }

    $unitPrice = round(floatval($unitPrice), 2);


    /*
     * Stock Qty
     */

    if (!is_numeric($stockQty) || intval($stockQty) < 0) {

        throw new Exception(
            "Invalid Stock Quantity"
        );
    }

    $stockQty = intval($stockQty);


    /*
     * Get Product
     */

    $stmt = $conn->prepare("
        SELECT
            Product_ID,
            Product_Description
        FROM products
        WHERE Product_ID = ?
    ");

    $stmt->bind_param("s", $productId);

    $stmt->execute();

    $result = $stmt->get_result();

    $product = $result->fetch_assoc();

    $stmt->close();


    if (!$product) {

        throw new Exception(
            "Product does not exist: " . $productId
        );
    }


    /*
     * Get Item Name from Database
     */

    $itemName = $product["Product_Description"];


    /*
     * Calculate Sub Total
     */

    $subTotal = round(
        $quantity * $unitPrice,
        2
    );


    /*
     * Calculate Total
     */

    $totalQty += $quantity;

    $totalAmount += $subTotal;


    /*
     * Store validated item
     */

    $validatedItems[] = [

        "product_id" => $productId,

        "item_name" => $itemName,

        "quantity" => $quantity,

        "uom" => $uom,

        "unit_price" => $unitPrice,

        "sub_total" => $subTotal,

        "stock_qty" => $stockQty

    ];
}


$totalAmount = round($totalAmount, 2);


/*
|--------------------------------------------------------------------------
| Generate PO Number
|--------------------------------------------------------------------------
|
| Format:
| PO-MYGYY-MM-000
|
| Example:
| PO-MYG26-08-001
|
|--------------------------------------------------------------------------
*/

$year = date("y");

$month = date("m");

$prefix = "PO-MYG" . $year . "-" . $month . "-";


$stmt = $conn->prepare("
    SELECT PO_ID
    FROM purchase_orders
    WHERE PO_ID LIKE ?
    ORDER BY PO_ID DESC
    LIMIT 1
");

$likePrefix = $prefix . "%";

$stmt->bind_param("s", $likePrefix);

$stmt->execute();

$result = $stmt->get_result();

$lastPO = $result->fetch_assoc();

$stmt->close();


if ($lastPO) {

    $lastNumber = intval(
        substr($lastPO["PO_ID"], -3)
    );

    $nextNumber = $lastNumber + 1;

} else {

    $nextNumber = 1;
}


if ($nextNumber > 999) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Monthly PO limit of 999 reached"
    ]);

    exit;
}


$poNumber =
    $prefix .
    str_pad(
        $nextNumber,
        3,
        "0",
        STR_PAD_LEFT
    );


/*
|--------------------------------------------------------------------------
| Database Transaction
|--------------------------------------------------------------------------
*/

try {

    $conn->begin_transaction();


    /*
     * Insert PO Header
     */

    $stmt = $conn->prepare("
        INSERT INTO purchase_orders (
            PO_ID,
            Vendor_ID,
            PO_Date,
            Total_Amount,
            Total_Qty,
            Status,
            Created_By,
            Prepared_Date,
            Ref_No,
            Description,
            Currency
        )
        VALUES (
            ?,
            ?,
            CURDATE(),
            ?,
            ?,
            'DRAFT',
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    $stmt->bind_param(
        "ssdisssss",
        $poNumber,
        $vendorId,
        $totalAmount,
        $totalQty,
        $createdBy,
        $preparedDate,
        $refNo,
        $description,
        $currency
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to insert PO: " .
            $stmt->error
        );
    }


    $stmt->close();


    /*
     * Insert PO Items
     */

    $stmt = $conn->prepare("
        INSERT INTO purchase_order_items (
            PO_ID,
            Product_ID,
            Item_Name,
            Quantity,
            UOM,
            Unit_Price,
            Sub_Total,
            Stock_Qty
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    foreach ($validatedItems as $item) {

        $stmt->bind_param(
            "sssissdi",
            $poNumber,
            $item["product_id"],
            $item["item_name"],
            $item["quantity"],
            $item["uom"],
            $item["unit_price"],
            $item["sub_total"],
            $item["stock_qty"]
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Failed to insert PO Item: " .
                $stmt->error
            );
        }
    }


    $stmt->close();


    /*
     * Everything successful
     */

    $conn->commit();


    echo json_encode([

        "success" => true,

        "message" => "PO created successfully",

        "data" => [

            "po_id" => $poNumber,

            "total_qty" => $totalQty,

            "total_amount" => $totalAmount

        ]

    ]);


} catch (Exception $e) {

    $conn->rollback();

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" => $e->getMessage()

    ]);
}

?>