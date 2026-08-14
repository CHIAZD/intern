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


if (!is_array($input)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request data"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Data
|--------------------------------------------------------------------------
*/

$vendorId =
    trim($input["vendor_id"] ?? "");

$companyName =
    trim($input["company_name"] ?? "");

$pic =
    trim($input["pic"] ?? "");

$address =
    trim($input["address"] ?? "");

$tel =
    trim($input["tel"] ?? "");

$factory =
    trim($input["factory"] ?? "");

$purchaserCode =
    trim($input["purchaser_code"] ?? "");


/*
|--------------------------------------------------------------------------
| Required Fields
|--------------------------------------------------------------------------
*/

if ($vendorId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Vendor ID is required"
    ]);

    exit;
}


if ($companyName === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Vendor Company Name is required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Length Validation
|--------------------------------------------------------------------------
*/

if (strlen($vendorId) > 20) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Vendor ID must not exceed 20 characters"
    ]);

    exit;
}


if (strlen($companyName) > 255) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Company Name must not exceed 255 characters"
    ]);

    exit;
}


if (strlen($pic) > 100) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "PIC must not exceed 100 characters"
    ]);

    exit;
}


if (strlen($address) > 500) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Address must not exceed 500 characters"
    ]);

    exit;
}


if (strlen($tel) > 50) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Telephone must not exceed 50 characters"
    ]);

    exit;
}


if (strlen($factory) > 255) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Factory must not exceed 255 characters"
    ]);

    exit;
}


if (strlen($purchaserCode) > 50) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Purchaser Code must not exceed 50 characters"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Duplicate Vendor
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT Vendor_ID
    FROM vendors
    WHERE Vendor_ID = ?
");

$stmt->bind_param(
    "s",
    $vendorId
);

$stmt->execute();

$result =
    $stmt->get_result();

$stmt->close();


if ($result->num_rows > 0) {

    http_response_code(409);

    echo json_encode([
        "success" => false,
        "message" => "Vendor ID already exists"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Insert Vendor
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    INSERT INTO vendors
    (
        Vendor_ID,
        Vendor_CompanyName,
        Vendor_PIC,
        Vendor_Address,
        Vendor_Tel,
        Vendor_Factory,
        Purchaser_Code
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)
");


$stmt->bind_param(
    "sssssss",
    $vendorId,
    $companyName,
    $pic,
    $address,
    $tel,
    $factory,
    $purchaserCode
);


if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to create vendor: " .
            $stmt->error
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Audit Log
|--------------------------------------------------------------------------
*/

$action =
    "CREATE";

$module =
    "VENDOR";

$recordId =
    $vendorId;

$oldValue =
    null;

$newValue =
    json_encode([

        "vendor_id" =>
            $vendorId,

        "company_name" =>
            $companyName,

        "pic" =>
            $pic,

        "address" =>
            $address,

        "tel" =>
            $tel,

        "factory" =>
            $factory,

        "purchaser_code" =>
            $purchaserCode
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
    $recordId,
    $oldValue,
    $newValue
);


if (!$stmt->execute()) {

    $stmt->close();

    echo json_encode([
        "success" => true,
        "message" =>
            "Vendor created successfully, but audit log failed",
        "data" => [
            "vendor_id" =>
                $vendorId
        ]
    ]);

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
        "Vendor created successfully",

    "data" => [

        "vendor_id" =>
            $vendorId

    ]

]);

?>