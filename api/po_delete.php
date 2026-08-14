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


$userId =
    $_SESSION["user_id"];

$userLevel =
    (int)$_SESSION["user_level"];


/*
|--------------------------------------------------------------------------
| Level 2 Only
|--------------------------------------------------------------------------
*/

if ($userLevel !== 2) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" =>
            "Permission denied. Level 2 is required."
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
        "message" =>
            "Only POST method is allowed"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get JSON
|--------------------------------------------------------------------------
*/

$input =
    json_decode(
        file_get_contents("php://input"),
        true
    );


$poId =
    trim($input["po_id"] ?? "");


if ($poId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "PO ID is required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

try {

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Get PO Header
    |--------------------------------------------------------------------------
    */

    $stmt =
        $conn->prepare("
            SELECT *
            FROM purchase_orders
            WHERE PO_ID = ?
            FOR UPDATE
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

        throw new Exception(
            "PO not found"
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get PO Items
    |--------------------------------------------------------------------------
    */

    $stmt =
        $conn->prepare("
            SELECT
                Product_ID,
                Item_Name,
                Quantity,
                UOM,
                Unit_Price,
                Sub_Total,
                Stock_Qty
            FROM purchase_order_items
            WHERE PO_ID = ?
        ");


    $stmt->bind_param(
        "s",
        $poId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $items = [];


    while (
        $row =
            $result->fetch_assoc()
    ) {

        $items[] =
            $row;
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Prepare Old Value For Audit
    |--------------------------------------------------------------------------
    */

    $oldValue =
        json_encode([

            "po_id" =>
                $po["PO_ID"],

            "po_date" =>
                $po["PO_Date"],

            "vendor_id" =>
                $po["Vendor_ID"],

            "ref_no" =>
                $po["Ref_No"],

            "currency" =>
                $po["Currency"],

            "total_qty" =>
                $po["Total_Qty"],

            "total_amount" =>
                $po["Total_Amount"],

            "prepared_date" =>
                $po["Prepared_Date"],

            "description" =>
                $po["Description"],

            "status" =>
                $po["Status"],

            "created_by" =>
                $po["Created_By"],

            "created_at" =>
                $po["Created_At"],

            "items" =>
                $items

        ]);


    /*
    |--------------------------------------------------------------------------
    | Delete PO Items
    |--------------------------------------------------------------------------
    */

    $stmt =
        $conn->prepare("
            DELETE FROM purchase_order_items
            WHERE PO_ID = ?
        ");


    $stmt->bind_param(
        "s",
        $poId
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to delete PO items: " .
            $stmt->error
        );
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete PO Header
    |--------------------------------------------------------------------------
    */

    $stmt =
        $conn->prepare("
            DELETE FROM purchase_orders
            WHERE PO_ID = ?
        ");


    $stmt->bind_param(
        "s",
        $poId
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Failed to delete PO: " .
            $stmt->error
        );
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    $action =
        "DELETE";

    $module =
        "PO";

    $recordId =
        $poId;

    $newValue =
        null;


    $stmt =
        $conn->prepare("
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
        $recordId,
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


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "message" =>
            "PO deleted successfully",

        "po_id" =>
            $poId

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