<?php
require_once '../../session.php';

if (!isset($_SESSION['last_activity']) || time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    header('Location: ../../login.php');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    exit;
}
?>
<?php

header('Content-Type: text/html; charset=UTF-8');

$versionFile = __DIR__ . '/version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
$pageTitle = 'Projects Monitor API v' . htmlspecialchars($version) . ' - Swagger UI';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui.css"
        integrity="sha384-9Q2fpS+xeS4ffJy6CagnwoUl+4ldAYhOs9pgZuEKxypVModhmZFzeMlvVsAjf7uT" crossorigin="anonymous">

    <style>
        body {
            margin: 0;
            background: #1a1a2e;
        }

        .swagger-ui .topbar {
            background-color: #16213e;
        }

        .swagger-ui .topbar .download-url-wrapper {
            display: none;
        }

        #swagger-ui {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem 2rem;
        }

        .api-header {
            background: #16213e;
            color: #e0e0e0;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-family: Inter, sans-serif;
            font-size: 0.95rem;
        }

        .api-header a {
            color: #7ec8e3;
            text-decoration: none;
        }

        .api-header a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="api-header">
        <a href="../../index.php">&larr; Dashboard</a>
        <span style="color:#555">|</span>
        <span>Projects Monitor &mdash; API Documentation</span>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui-bundle.js"
        integrity="sha384-EYdOaiRwn44zNjrw+Tfs06qYz9BGQVo2f4/pLY5i7VorbjnZNhdplAbTBk8FXHUJ"
        crossorigin="anonymous"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui-standalone-preset.js"
        integrity="sha384-49fpFaVrAWI/qdgl9Vv5E/4NXxRUiJX5vGuLws1NUpTWGtEqzWEx8gHTw2UTehFK"
        crossorigin="anonymous"></script>
    <script>
        window.onload = function () {
            SwaggerUIBundle({
                url: '/projects-monitor/api/v1/openapi',
                dom_id: '#swagger-ui',
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                layout: 'StandaloneLayout',
                deepLinking: true,
                displayRequestDuration: true,
                filter: true
            });
        };
    </script>
</body>

</html>