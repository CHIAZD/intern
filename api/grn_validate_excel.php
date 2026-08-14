<?php

session_start();

require_once "../config.php";

header("Content-Type: application/json");


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


if (!$input || !isset($input["rows"])) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid Excel data"
    ]);

    exit;
}


$rows = $input["rows"];


if (!is_array($rows) || count($rows) === 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Excel contains no data"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validation Result
|--------------------------------------------------------------------------
*/

$errors = [];

$items = [];


/*
|--------------------------------------------------------------------------
| USED PO ITEMS
|--------------------------------------------------------------------------
|
| Important:
|
| A PO Item can only be successfully matched ONCE
| during this Excel validation.
|
| Example:
|
| PO-001
| I001
| PO_Item_ID = 123
|
| Excel:
|
| Row 1 -> I001 / 1
| Row 2 -> I001 / 1
|
| Row 1 succeeds:
| PO_Item_ID 123 becomes USED.
|
| Row 2 cannot use PO_Item_ID 123 again.
|
*/

$usedPOItems = [];


/*
|--------------------------------------------------------------------------
| Process Every Excel Row
|--------------------------------------------------------------------------
*/

foreach ($rows as $index => $excelRow) {


    /*
    |--------------------------------------------------------------------------
    | Excel Header
    |--------------------------------------------------------------------------
    |
    | PO Number
    | Item Code
    | QTY
    |
    */

    $rowNumber = $index + 2;


    /*
    |--------------------------------------------------------------------------
    | Get Excel Data
    |--------------------------------------------------------------------------
    */

    $poId = trim(
        $excelRow["PO Number"] ?? ""
    );


    $itemCode = trim(
        $excelRow["Item Code"] ?? ""
    );


    $excelQty = $excelRow["QTY"] ?? "";


    /*
    |--------------------------------------------------------------------------
    | Basic Validation - PO Number
    |--------------------------------------------------------------------------
    */

    if ($poId === "") {

        $errors[] = [

            "row" => $rowNumber,

            "item_code" => $itemCode,

            "message" =>
                "PO Number is missing."

        ];

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | Basic Validation - Item Code
    |--------------------------------------------------------------------------
    */

    if ($itemCode === "") {

        $errors[] = [

            "row" => $rowNumber,

            "item_code" => "",

            "message" =>
                "Item Code is missing."

        ];

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | Basic Validation - QTY
    |--------------------------------------------------------------------------
    */

    if (
        $excelQty === "" ||
        !is_numeric($excelQty) ||
        (int)$excelQty <= 0
    ) {

        $errors[] = [

            "row" => $rowNumber,

            "item_code" => $itemCode,

            "message" =>
                "QTY must be a positive number."

        ];

        continue;
    }


    $excelQty = (int)$excelQty;


    /*
    |--------------------------------------------------------------------------
    | Find UNUSED PO Item
    |--------------------------------------------------------------------------
    |
    | Important:
    |
    | We DO NOT use LIMIT 1 here.
    |
    | We check all matching PO Items and find
    | one that has NOT already been successfully
    | used during this Excel validation.
    |
    */

    $stmt = $conn->prepare("

        SELECT

            poi.PO_Item_ID,

            poi.PO_ID,

            poi.Product_ID,

            poi.Item_Name,

            poi.Quantity,

            poi.UOM,

            poi.Unit_Price,

            poi.Stock_Qty,

            p.Product_Description,

            p.ProductPcsperCarton

        FROM purchase_order_items poi

        LEFT JOIN products p

            ON p.Product_ID = poi.Product_ID

        WHERE poi.PO_ID = ?

        AND poi.Product_ID = ?

        ORDER BY poi.PO_Item_ID ASC

    ");


    if (!$stmt) {

        $errors[] = [

            "row" => $rowNumber,

            "item_code" => $itemCode,

            "message" =>
                "Failed to prepare PO item query."

        ];

        continue;
    }


    $stmt->bind_param(
        "ss",
        $poId,
        $itemCode
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    /*
    |--------------------------------------------------------------------------
    | Find First UNUSED PO Item
    |--------------------------------------------------------------------------
    */

    $poItem = null;


    while (
        $candidate =
        $result->fetch_assoc()
    ) {

        $candidateId =
            (int)$candidate["PO_Item_ID"];


        /*
        |--------------------------------------------------------------------------
        | Check Whether This PO Item Was Already Used
        |--------------------------------------------------------------------------
        */

        if (
            isset($usedPOItems[$poId]) &&
            in_array(
                $candidateId,
                $usedPOItems[$poId],
                true
            )
        ) {

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Found UNUSED PO Item
        |--------------------------------------------------------------------------
        */

        $poItem =
            $candidate;

        break;
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | PO / Item Not Found
    |--------------------------------------------------------------------------
    */

    if (!$poItem) {

        $errors[] = [

            "row" => $rowNumber,

            "item_code" => $itemCode,

            "message" =>
                "PO Number or available Item Code does not exist in the PO, or the PO Item has already been used in this Excel."

        ];

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | PO Quantity
    |--------------------------------------------------------------------------
    */

    $poQty =
        (int)$poItem["Quantity"];


    /*
    |--------------------------------------------------------------------------
    | Get Already Received GRN Quantity
    |--------------------------------------------------------------------------
    |
    | This checks previous GRNs.
    |
    */

    $stmt = $conn->prepare("

        SELECT

            COALESCE(
                SUM(Quantity),
                0
            ) AS received_qty

        FROM grn_items

        WHERE PO_ID = ?

        AND PO_Item_ID = ?

    ");


    if (!$stmt) {

        $errors[] = [

            "row" => $rowNumber,

            "item_code" => $itemCode,

            "message" =>
                "Failed to check existing GRN quantity."

        ];

        continue;
    }


    $poItemId =
        (int)$poItem["PO_Item_ID"];


    $stmt->bind_param(
        "si",
        $poId,
        $poItemId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $received =
        $result->fetch_assoc();


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Already Received Quantity
    |--------------------------------------------------------------------------
    */

    $receivedQty =
        (int)($received["received_qty"] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Remaining PO Quantity
    |--------------------------------------------------------------------------
    */

    $remainingQty =
        $poQty - $receivedQty;


    /*
    |--------------------------------------------------------------------------
    | Excel Quantity Cannot Exceed Remaining Quantity
    |--------------------------------------------------------------------------
    */

    if ($excelQty > $remainingQty) {

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | This comparison FAILED.
        |
        | Therefore:
        |
        | DO NOT mark PO_Item_ID as USED.
        |
        */

        $errors[] = [

            "row" => $rowNumber,

            "item_code" => $itemCode,

            "message" =>
                "Excel QTY ($excelQty) exceeds remaining PO quantity ($remainingQty). " .
                "PO Qty: $poQty, Already Received: $receivedQty."

        ];

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | COMPARISON SUCCESSFUL
    |--------------------------------------------------------------------------
    |
    | Only NOW do we mark this PO Item as USED.
    |
    */

    if (
        !isset(
            $usedPOItems[$poId]
        )
    ) {

        $usedPOItems[$poId] = [];
    }


    $usedPOItems[$poId][] =
        $poItemId;


    /*
    |--------------------------------------------------------------------------
    | Product Packing Size
    |--------------------------------------------------------------------------
    */

    $qtyPerCarton =
        (int)(
            $poItem["ProductPcsperCarton"]
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | Total QTY
    |--------------------------------------------------------------------------
    */

    $totalQty =
        $excelQty *
        $qtyPerCarton;


    /*
    |--------------------------------------------------------------------------
    | Unit Price
    |--------------------------------------------------------------------------
    */

    $unitPrice =
        (float)(
            $poItem["Unit_Price"]
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | Sub Total
    |--------------------------------------------------------------------------
    */

    $subTotal =
        $totalQty *
        $unitPrice;


    /*
    |--------------------------------------------------------------------------
    | Add Valid Item
    |--------------------------------------------------------------------------
    */

    $items[] = [

        "po_id" =>
            $poItem["PO_ID"],

        "po_item_id" =>
            $poItemId,

        "product_id" =>
            $poItem["Product_ID"],

        "item_name" =>
            $poItem["Item_Name"],

        "quantity" =>
            $excelQty,

        "uom" =>
            $poItem["UOM"],

        "qty_per_carton" =>
            $qtyPerCarton,

        "total_qty" =>
            $totalQty,

        "unit_price_myr" =>
            $unitPrice,

        "sub_total" =>
            $subTotal

    ];

}


/*
|--------------------------------------------------------------------------
| IMPORTANT:
| If ANY row fails, reject ENTIRE Excel file.
|--------------------------------------------------------------------------
*/

if (count($errors) > 0) {

    echo json_encode([

        "success" => false,

        "message" =>
            "Excel validation failed. No GRN items were imported.",

        "errors" =>
            $errors

    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| All Rows Passed
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "message" =>
        "Excel validation successful.",

    "items" =>
        $items

]);

?>