<?php

$hash = '$2y$12$xXOkCEJhKLZD/3xONVpBJ.rAqNDOhYU2XAjuuWPc1rWNMhV5NYt0O';

echo "Length: " . strlen($hash) . "<br>";

echo password_verify("123456", $hash)
    ? "PASSWORD CORRECT"
    : "PASSWORD WRONG";

?>