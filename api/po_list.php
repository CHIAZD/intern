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
| Get Search / Filter Parameters
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET["search"] ?? "");

$vendorId =
    trim($_GET["vendor_id"] ?? "");

$status =
    trim($_GET["status"] ?? "");

$dateFrom =
    trim($_GET["date_from"] ?? "");

$dateTo =
    trim($_GET["date_to"] ?? "");


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        po.PO_ID,
        po.PO_Date,
        po.Vendor_ID,
        v.Vendor_CompanyName,
        po.Ref_No,
        po.Currency,
        po.Total_Qty,
        po.Total_Amount,
        po.Status,
        po.Created_By,
        po.Created_At

    FROM purchase_orders po

    LEFT JOIN vendors v
        ON po.Vendor_ID = v.Vendor_ID

    WHERE 1 = 1
";


$params = [];

$types = "";


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
|
| Search:
| PO ID
| Vendor ID
| Vendor Company Name
| Ref No
|
*/

if ($search !== "") {

    $sql .= "
        AND (
            po.PO_ID LIKE ?
            OR po.Vendor_ID LIKE ?
            OR v.Vendor_CompanyName LIKE ?
            OR po.Ref_No LIKE ?
        )
    ";

    $searchValue =
        "%" . $search . "%";


    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;


    $types .= "ssss";
}


/*
|--------------------------------------------------------------------------
| Vendor Filter
|--------------------------------------------------------------------------
*/

if ($vendorId !== "") {

    $sql .= "
        AND po.Vendor_ID = ?
    ";

    $params[] =
        $vendorId;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== "") {

    $sql .= "
        AND po.Status = ?
    ";

    $params[] =
        $status;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| Date From
|--------------------------------------------------------------------------
*/

if ($dateFrom !== "") {

    $sql .= "
        AND po.PO_Date >= ?
    ";

    $params[] =
        $dateFrom;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| Date To
|--------------------------------------------------------------------------
*/

if ($dateTo !== "") {

    $sql .= "
        AND po.PO_Date <= ?
    ";

    $params[] =
        $dateTo;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        po.Created_At DESC
";


/*
|--------------------------------------------------------------------------
| Prepare
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to prepare query: " .
            $conn->error
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Bind Parameters
|--------------------------------------------------------------------------
*/

if (count($params) > 0) {

    $bindParams = [];

    $bindParams[] =
        $types;


    foreach ($params as $key => $value) {

        $bindParams[] =
            &$params[$key];

    }


    call_user_func_array(
        [$stmt, "bind_param"],
        $bindParams
    );
}


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
            "Failed to load PO list: " .
            $stmt->error
    ]);

    $stmt->close();

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Result
|--------------------------------------------------------------------------
*/

$result =
    $stmt->get_result();


$rows = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $rows[] =
        $row;

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "data" =>
        $rows,

    "count" =>
        count($rows)

]);

?>