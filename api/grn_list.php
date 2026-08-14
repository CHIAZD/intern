<?php

session_start();

require_once "../config.php";

header("Content-Type: application/json");


/*
|--------------------------------------------------------------------------
| GET ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only GET request is allowed"
    ]);

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | Get GRN List
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT

            GRN_ID,
            GRN_Date,
            Total_Amount,
            Status,
            Created_At,
            Created_By,
            Photo_Path,
            ETD_Date,
            ETA_Date,
            Exchange_Rate,
            Entry,
            Cost_Per_Entry,
            Carton_Number

        FROM grns

        ORDER BY Created_At DESC

    ";


    $result =
        $conn->query($sql);


    if (!$result) {

        throw new Exception(
            "Failed to retrieve GRN list: "
            . $conn->error
        );

    }


    $grns = [];


    while (
        $row =
        $result->fetch_assoc()
    ) {

        $grns[] = $row;

    }


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "data" => $grns

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