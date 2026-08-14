<?php

session_start();

header("Content-Type: application/json");


/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_level"])
) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "logged_in" => false,
        "message" => "User is not logged in"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Session Data
|--------------------------------------------------------------------------
*/

$userId =
    $_SESSION["user_id"];

$username =
    $_SESSION["username"] ?? "";

$fullName =
    $_SESSION["full_name"] ?? "";

$userLevel =
    (int)$_SESSION["user_level"];


/*
|--------------------------------------------------------------------------
| Validate User Level
|--------------------------------------------------------------------------
*/

if (
    $userLevel !== 1 &&
    $userLevel !== 2
) {

    session_unset();
    session_destroy();

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "logged_in" => false,
        "message" => "Invalid user level"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "logged_in" => true,

    "data" => [

        "user_id" =>
            $userId,

        "username" =>
            $username,

        "full_name" =>
            $fullName,

        "user_level" =>
            $userLevel

    ]

]);

?>