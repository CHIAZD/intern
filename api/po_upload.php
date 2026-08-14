<?php

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
| Get PO ID
|--------------------------------------------------------------------------
*/

$poId = $_POST["po_id"] ?? "";

if ($poId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "PO ID is required"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check File
|--------------------------------------------------------------------------
*/

if (!isset($_FILES["photo"])) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "No photo uploaded"
    ]);

    exit;
}


$file = $_FILES["photo"];


/*
|--------------------------------------------------------------------------
| Check Upload Error
|--------------------------------------------------------------------------
*/

if ($file["error"] !== UPLOAD_ERR_OK) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "File upload failed. Error code: " .
            $file["error"]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| File Size
|--------------------------------------------------------------------------
*/

$maxSize = 5 * 1024 * 1024;

if ($file["size"] > $maxSize) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Photo must be smaller than 5 MB"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check File Type
|--------------------------------------------------------------------------
*/

$allowedTypes = [

    "image/jpeg" => "jpg",

    "image/png" => "png",

    "image/webp" => "webp"

];


$finfo =
    finfo_open(FILEINFO_MIME_TYPE);


$mimeType =
    finfo_file(
        $finfo,
        $file["tmp_name"]
    );


finfo_close($finfo);


if (!isset($allowedTypes[$mimeType])) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Only JPG, PNG and WEBP images are allowed"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check PO Exists
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        PO_ID,
        Photo_Path
    FROM purchase_orders
    WHERE PO_ID = ?
");


$stmt->bind_param(
    "s",
    $poId
);


$stmt->execute();


$result =
    $stmt->get_result();


$po =
    $result->fetch_assoc();


$stmt->close();


if (!$po) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "PO not found"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Upload Directory
|--------------------------------------------------------------------------
*/

$uploadDirectory =
    dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    "uploads";


/*
|--------------------------------------------------------------------------
| Create Directory
|--------------------------------------------------------------------------
*/

if (!is_dir($uploadDirectory)) {

    if (!mkdir(
        $uploadDirectory,
        0777,
        true
    )) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" =>
                "Failed to create upload directory",

            "debug" => [
                "upload_directory" =>
                    $uploadDirectory,

                "parent_directory" =>
                    dirname($uploadDirectory),

                "parent_writable" =>
                    is_writable(
                        dirname($uploadDirectory)
                    )
            ]
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Check Directory
|--------------------------------------------------------------------------
*/

if (!is_dir($uploadDirectory)) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Upload directory does not exist",

        "debug" => [
            "upload_directory" =>
                $uploadDirectory
        ]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Directory Writable
|--------------------------------------------------------------------------
*/

if (!is_writable($uploadDirectory)) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Upload directory is not writable",

        "debug" => [
            "upload_directory" =>
                $uploadDirectory,

            "is_dir" =>
                is_dir($uploadDirectory),

            "is_writable" =>
                is_writable($uploadDirectory),

            "permissions" =>
                substr(
                    sprintf(
                        "%o",
                        fileperms(
                            $uploadDirectory
                        )
                    ),
                    -4
                )
        ]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Generate Safe File Name
|--------------------------------------------------------------------------
*/

$extension =
    $allowedTypes[$mimeType];


$fileName =
    $poId .
    "_" .
    bin2hex(
        random_bytes(8)
    ) .
    "." .
    $extension;


$filePath =
    $uploadDirectory .
    DIRECTORY_SEPARATOR .
    $fileName;


/*
|--------------------------------------------------------------------------
| Move File
|--------------------------------------------------------------------------
*/

$moveResult =
    move_uploaded_file(
        $file["tmp_name"],
        $filePath
    );


if (!$moveResult) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to save photo",

        "debug" => [

            "upload_directory" =>
                $uploadDirectory,

            "file_path" =>
                $filePath,

            "tmp_file" =>
                $file["tmp_name"],

            "tmp_exists" =>
                file_exists(
                    $file["tmp_name"]
                ),

            "directory_exists" =>
                is_dir(
                    $uploadDirectory
                ),

            "directory_writable" =>
                is_writable(
                    $uploadDirectory
                ),

            "file_exists_after_move" =>
                file_exists(
                    $filePath
                )

        ]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Database Path
|--------------------------------------------------------------------------
*/

$databasePath =
    "uploads/" .
    $fileName;


/*
|--------------------------------------------------------------------------
| Update Database
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE purchase_orders

    SET Photo_Path = ?

    WHERE PO_ID = ?
");


$stmt->bind_param(
    "ss",
    $databasePath,
    $poId
);


if (!$stmt->execute()) {

    if (file_exists($filePath)) {

        unlink($filePath);

    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to update photo path: " .
            $stmt->error
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Delete Old Photo
|--------------------------------------------------------------------------
*/

$oldPhoto =
    $po["Photo_Path"] ?? "";


if (
    $oldPhoto !== "" &&
    $oldPhoto !== $databasePath
) {

    $oldFile =
        dirname(__DIR__) .
        DIRECTORY_SEPARATOR .
        str_replace(
            "/",
            DIRECTORY_SEPARATOR,
            $oldPhoto
        );


    if (
        file_exists($oldFile) &&
        is_file($oldFile)
    ) {

        unlink($oldFile);

    }

}


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "message" =>
        "Photo uploaded successfully",

    "data" => [

        "po_id" =>
            $poId,

        "photo_path" =>
            $databasePath

    ]

]);

?>