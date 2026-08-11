<?php

require_once "../config.php";

header("Content-Type: application/json");

$year = date("y");
$month = date("m");

$prefix = "PO-MYG" . $year . "-" . $month . "-";

/*
|--------------------------------------------------------------------------
| Find the latest PO number for this year/month
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT PO_ID
    FROM purchase_orders
    WHERE PO_ID LIKE ?
    ORDER BY PO_ID DESC
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$search = $prefix . "%";

$stmt->bind_param("s", $search);

$stmt->execute();

$result = $stmt->get_result();

$nextNumber = 1;

if ($row = $result->fetch_assoc()) {

    $lastPO = $row["PO_ID"];

    $lastNumber = (int)substr($lastPO, -3);

    $nextNumber = $lastNumber + 1;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Maximum 999
|--------------------------------------------------------------------------
*/

if ($nextNumber > 999) {

    echo json_encode([
        "success" => false,
        "message" => "PO number limit reached for this month."
    ]);

    exit;
}


$poNumber =
    $prefix . str_pad(
        $nextNumber,
        3,
        "0",
        STR_PAD_LEFT
    );


echo json_encode([
    "success" => true,
    "PO_ID" => $poNumber,
    "PO_Date" => date("Y-m-d")
]);

?>