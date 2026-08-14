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
        "message" => "Only POST method is allowed"
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

$productId =
    trim(
        $input["product_id"] ?? ""
    );


$productDescription =
    trim(
        $input["product_description"] ?? ""
    );


$productPackingSize =
    isset($input["product_packing_size"])
        ? trim($input["product_packing_size"])
        : null;


$productPcsPerCarton =
    $input["product_pcs_per_carton"] ?? null;


/*
|--------------------------------------------------------------------------
| Validate Required Fields
|--------------------------------------------------------------------------
*/

if ($productId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Product ID is required"
    ]);

    exit;
}


if ($productDescription === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Product Description is required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Product ID Length
|--------------------------------------------------------------------------
*/

if (strlen($productId) > 20) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Product ID cannot exceed 20 characters"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Pcs Per Carton
|--------------------------------------------------------------------------
*/

if (
    $productPcsPerCarton !== null &&
    $productPcsPerCarton !== ""
) {

    if (
        !is_numeric($productPcsPerCarton) ||
        intval($productPcsPerCarton) < 0 ||
        intval($productPcsPerCarton) != $productPcsPerCarton
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" =>
                "Product Pcs per Carton must be a positive integer or 0"
        ]);

        exit;
    }

    $productPcsPerCarton =
        intval($productPcsPerCarton);

} else {

    $productPcsPerCarton = null;

}


/*
|--------------------------------------------------------------------------
| Check Duplicate Product
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT Product_ID
        FROM products
        WHERE Product_ID = ?
    ");


$stmt->bind_param(
    "s",
    $productId
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows > 0) {

    $stmt->close();

    http_response_code(409);

    echo json_encode([
        "success" => false,
        "message" =>
            "Product ID already exists"
    ]);

    exit;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Insert Product
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        INSERT INTO products
        (
            Product_ID,
            Product_Description,
            Product_PackingSize,
            ProductPcsperCarton
        )

        VALUES (?, ?, ?, ?)
    ");


$stmt->bind_param(
    "sssi",
    $productId,
    $productDescription,
    $productPackingSize,
    $productPcsPerCarton
);


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to create product: " .
            $error
    ]);

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
    "PRODUCT";

$recordId =
    $productId;

$newValue =
    json_encode([
        "product_id" =>
            $productId,

        "product_description" =>
            $productDescription,

        "product_packing_size" =>
            $productPackingSize,

        "product_pcs_per_carton" =>
            $productPcsPerCarton
    ]);


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


$oldValue = null;


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

    /*
    |--------------------------------------------------------------------------
    | Product was already created.
    | Audit failure should be reported.
    |--------------------------------------------------------------------------
    */

    $error =
        $stmt->error;

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Product created, but audit log failed: " .
            $error
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
        "Product created successfully",

    "data" => [

        "product_id" =>
            $productId

    ]

]);

?>