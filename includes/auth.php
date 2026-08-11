<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function requireLogin()
{
    if (!isset($_SESSION["user_id"])) {

        header("Location: ../login.php");

        exit;
    }
}


function getUserLevel()
{
    return $_SESSION["user_level"] ?? 0;
}


function isLevel2()
{
    return getUserLevel() >= 2;
}


function requireLevel2()
{
    requireLogin();

    if (!isLevel2()) {

        http_response_code(403);

        die("Access Denied");
    }
}

?>