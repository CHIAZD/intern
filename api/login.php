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
| Username / Password
|--------------------------------------------------------------------------
*/

$username =
    trim($input["username"] ?? "");

$password =
    $input["password"] ?? "";


if ($username === "" || $password === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Username and password are required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Find User
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        User_ID,
        Username,
        Password_Hash,
        Full_Name,
        User_Level,
        Status
    FROM users
    WHERE Username = ?
    LIMIT 1
");


$stmt->bind_param(
    "s",
    $username
);


$stmt->execute();


$result =
    $stmt->get_result();


$user =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| Check User
|--------------------------------------------------------------------------
*/

if (!$user) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Invalid username or password"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Status
|--------------------------------------------------------------------------
*/

if (
    strtoupper($user["Status"]) !==
    "ACTIVE"
) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "User account is inactive"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Password
|--------------------------------------------------------------------------
*/

if (
    !password_verify(
        $password,
        $user["Password_Hash"]
    )
) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Invalid username or password"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Login Success
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);


$_SESSION["user_id"] =
    $user["User_ID"];

$_SESSION["username"] =
    $user["Username"];

$_SESSION["full_name"] =
    $user["Full_Name"];

$_SESSION["user_level"] =
    (int)$user["User_Level"];


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "message" =>
        "Login successful",

    "data" => [

        "user_id" =>
            $user["User_ID"],

        "username" =>
            $user["Username"],

        "full_name" =>
            $user["Full_Name"],

        "user_level" =>
            (int)$user["User_Level"]

    ]

]);

?>