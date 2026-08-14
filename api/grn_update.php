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
        "message" => "Only POST request is allowed"
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
| GRN ID
|--------------------------------------------------------------------------
*/

$grnId =
    trim(
        $input["GRN_ID"] ?? ""
    );


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
| Editable Fields ONLY
|--------------------------------------------------------------------------
*/

$etdDate =
    $input["ETD_Date"] ?? null;


$etaDate =
    $input["ETA_Date"] ?? null;


$exchangeRate =
    $input["Exchange_Rate"] ?? null;


$entry =
    $input["Entry"] ?? null;


$costPerEntry =
    $input["Cost_Per_Entry"] ?? null;


$cartonNumber =
    $input["Carton_Number"] ?? null;


/*
|--------------------------------------------------------------------------
| Update GRN Header ONLY
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| We DO NOT update:
|
| GRN_ID
| GRN_Date
| Status
| Created_At
| Created_By
| Photo_Path
|
| We also DO NOT update grn_items.
|
|--------------------------------------------------------------------------
*/

$sql = "

    UPDATE grns

    SET

        ETD_Date = ?,

        ETA_Date = ?,

        Exchange_Rate = ?,

        Entry = ?,

        Cost_Per_Entry = ?,

        Carton_Number = ?

    WHERE GRN_ID = ?

";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to prepare update query: "
            . $conn->error
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Bind Parameters
|--------------------------------------------------------------------------
*/

$stmt->bind_param(
    "ssdssss",
    $etdDate,
    $etaDate,
    $exchangeRate,
    $entry,
    $costPerEntry,
    $cartonNumber,
    $grnId
);


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to update GRN: "
            . $stmt->error
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "message" =>
        "GRN updated successfully",

    "GRN_ID" =>
        $grnId

]);

?>