<?php

session_start();

require_once "../config.php";

header("Content-Type: application/json");


if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST request is allowed"
    ]);

    exit;
}


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


$grnId =
    trim(
        $input["GRN_ID"] ?? ""
    );


$grnDate =
    trim(
        $input["GRN_Date"] ?? ""
    );


$items =
    $input["Items"] ?? [];


if (
    $grnId === "" ||
    $grnDate === "" ||
    !is_array($items) ||
    count($items) === 0
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Missing required information"
    ]);

    exit;
}


$conn->begin_transaction();


try {

    /*
    |--------------------------------------------------------------------------
    | Check GRN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT Status
        FROM grns
        WHERE GRN_ID = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $grnId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $existing =
        $result->fetch_assoc();

    $stmt->close();


    if (!$existing) {

        throw new Exception(
            "GRN not found."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Only DRAFT can be edited
    |--------------------------------------------------------------------------
    */

    if (
        strtoupper(
            $existing["Status"]
        ) !== "DRAFT"
    ) {

        throw new Exception(
            "Only DRAFT GRN can be edited."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Total
    |--------------------------------------------------------------------------
    */

    $totalAmount = 0;


    foreach ($items as $item) {

        $subtotal =
            (float)(
                $item["Sub_Total"] ?? 0
            );


        $totalAmount +=
            $subtotal;

    }


    /*
    |--------------------------------------------------------------------------
    | Update Header
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE grns

        SET
            GRN_Date = ?,
            Total_Amount = ?,
            ETD_Date = ?,
            ETA_Date = ?,
            Exchange_Rate = ?,
            Entry = ?,
            Cost_Per_Entry = ?,
            Carton_Number = ?

        WHERE GRN_ID = ?
    ");


    $etdDate =
        $input["ETD_Date"] ?? null;

    $etaDate =
        $input["ETA_Date"] ?? null;

    $exchangeRate =
        isset(
            $input["Exchange_Rate"]
        )
        ? (float)$input["Exchange_Rate"]
        : null;

    $entry =
        $input["Entry"] ?? null;

    $costPerEntry =
        isset(
            $input["Cost_Per_Entry"]
        )
        ? (float)$input["Cost_Per_Entry"]
        : null;

    $cartonNumber =
        $input["Carton_Number"] ?? null;


    $stmt->bind_param(
        "sdssdsdss",
        $grnDate,
        $totalAmount,
        $etdDate,
        $etaDate,
        $exchangeRate,
        $entry,
        $costPerEntry,
        $cartonNumber,
        $grnId
    );


    $stmt->execute();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete Existing Items
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM grn_items
        WHERE GRN_ID = ?
    ");

    $stmt->bind_param(
        "s",
        $grnId
    );

    $stmt->execute();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Insert Updated Items
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
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
    ");


    foreach ($items as $item) {

        $poId =
            $item["PO_ID"];

        $poItemId =
            (int)$item["PO_Item_ID"];

        $productId =
            $item["Product_ID"];

        $itemName =
            $item["Item_Name"];

        $quantity =
            (int)$item["Quantity"];

        $uom =
            $item["UOM"];

        $qtyPerCarton =
            (int)$item["Qty_Per_Carton"];

        $totalQty =
            (int)$item["Total_Qty"];

        $unitPrice =
            (float)$item["Unit_Price_MYR"];

        $subTotal =
            (float)$item["Sub_Total"];


        $stmt->bind_param(
            "ssissisidd d",
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

        $stmt->execute();

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
        "message" => "GRN updated successfully.",
        "GRN_ID" => $grnId
    ]);

}
catch (Throwable $e) {

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}

?>