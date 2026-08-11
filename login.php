<?php

session_start();

require_once "config.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

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

        if (!$stmt) {

            $error = "Database query error: " . $conn->error;

        } else {

            $stmt->bind_param("s", $username);

            $stmt->execute();

            $result = $stmt->get_result();

            $user = $result->fetch_assoc();

            if (!$user) {

                $error = "Invalid username or password.";

            } elseif ($user["Status"] !== "ACTIVE") {

                $error = "This account is inactive.";

            } elseif (
                !password_verify(
                    $password,
                    $user["Password_Hash"]
                )
            ) {

                $error = "Invalid username or password.";

            } else {

                $_SESSION["user_id"] =
                    $user["User_ID"];

                $_SESSION["username"] =
                    $user["Username"];

                $_SESSION["full_name"] =
                    $user["Full_Name"];

                $_SESSION["user_level"] =
                    $user["User_Level"];

                header("Location: index.php");

                exit;
            }

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Purchasing System - Login</title>

</head>

<body>

    <h1>Purchasing System</h1>

    <h2>Login</h2>

    <?php if ($error !== ""): ?>

        <p>
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php endif; ?>

    <form method="POST">

        <label>Username</label>

        <br>

        <input
            type="text"
            name="username"
            required
        >

        <br><br>

        <label>Password</label>

        <br>

        <input
            type="password"
            name="password"
            required
        >

        <br><br>

        <button type="submit">
            Login
        </button>

    </form>

</body>

</html>