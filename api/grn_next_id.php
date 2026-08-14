<?php

session_start();

require_once "../config.php";

header("Content-Type: application/json");

try {

    /*
    |--------------------------------------------------------------------------
    | Current Date
    |--------------------------------------------------------------------------
    */

    $shortYear = date("y");
    $month = date("m");

    /*
    |--------------------------------------------------------------------------
    | Prefix
    |--------------------------------------------------------------------------
    |
    | Format:
    | GRN-MYG-YY-MM-
    |
    */

    $prefix = "GRN-MYG-" . $shortYear . "-" . $month . "-";

    /*
    |--------------------------------------------------------------------------
    | Find Latest Valid GRN For Current Month
    |--------------------------------------------------------------------------
    |
    | Only accept:
    |
    | GRN-MYG-26-08-001
    | GRN-MYG-26-08-002
    |
    | NOT:
    |
    | GRN-MYG-26-08-001-31
    |
    */

    $sql = "
        SELECT GRN_ID
        FROM grns
        WHERE GRN_ID REGEXP ?
        ORDER BY CAST(SUBSTRING(GRN_ID, -3) AS UNSIGNED) DESC
        LIMIT 1
    ";

    $pattern = "^" . $prefix . "[0-9]{3}$";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Failed to prepare GRN ID query: " . $conn->error
        );
    }

    $stmt->bind_param(
        "s",
        $pattern
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Calculate Next Number
    |--------------------------------------------------------------------------
    */

    if (!$row) {

        $nextNumber = 1;

    } else {

        $lastGRN = $row["GRN_ID"];

        $lastNumber = (int)substr(
            $lastGRN,
            -3
        );

        $nextNumber = $lastNumber + 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Maximum 999 Per Month
    |--------------------------------------------------------------------------
    */

    if ($nextNumber > 999) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "GRN number has reached the maximum limit of 999 for this month."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Format 3 Digits
    |--------------------------------------------------------------------------
    */

    $formattedNumber = str_pad(
        $nextNumber,
        3,
        "0",
        STR_PAD_LEFT
    );

    /*
    |--------------------------------------------------------------------------
    | Final GRN ID
    |--------------------------------------------------------------------------
    */

    $grnId = $prefix . $formattedNumber;

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "grn_id" => $grnId
    ]);

}
catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>