<?php

$host = "localhost";
$username = "zhengdao";
$password = "Intern4321!";
$database = "purchase_data";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>