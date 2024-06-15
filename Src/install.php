<?php

function checkIfIsTestEnvironment()
{
    $postman = isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], "PostmanRuntime") !== false;
    $localhost = $_SERVER["HTTP_HOST"] === "localhost:8003";
    $debug = isset($_GET["debug"]) && $_GET["debug"] == "true";

    if ($postman || $localhost || $debug) {
        return true;
    }

    return false;
}

function unzip($file, $destination)
{
    $zip = new ZipArchive();
    $res = $zip->open($file);

    if ($res !== true) {
        return false;
    }

    $zip->extractTo($destination);
    $zip->close();
    return true;
}

if (checkIfIsTestEnvironment()) {
    http_response_code(400);
    die("This is a test environment. Please use the production environment.");
}

$deployFile = "deploy.zip";
$installFile = "install.php";

if(!file_exists($deployFile)) {
    http_response_code(404);
    die("Deploy file not found.");
}

unzip($deployFile, "./");
unlink($deployFile);
unlink($installFile);
