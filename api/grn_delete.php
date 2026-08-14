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

    $grn =
        $result->fetch_assoc();

    $stmt->close();


    if (!$grn) {

        throw new Exception(
            "GRN not found."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Only DRAFT can be deleted
    |--------------------------------------------------------------------------
    */

    if (
        strtoupper(
            $grn["Status"]
        ) !== "DRAFT"
    ) {

        throw new Exception(
            "Only DRAFT GRN can be deleted."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Delete Items
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
    | Delete Header
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM grns
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
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    echo json_encode([
        "success" => true,
        "message" => "GRN deleted successfully."
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