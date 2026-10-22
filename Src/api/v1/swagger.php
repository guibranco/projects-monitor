<?php
require_once '../../session.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\SwaggerPagePresenter;

/*
 * The API reference is public: anyone can read the spec and browse the
 * endpoints. Only "Try it out" needs an active dashboard session (or
 * localhost:8000), which SwaggerPagePresenter works out from the request.
 */

$presenter = new SwaggerPagePresenter($_SESSION, ($_SERVER['HTTP_HOST'] ?? ''), time(), __DIR__.'/../../version.txt');

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');

$pageTitle = $presenter->getPageTitle();

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

        .api-header .session-status {
            margin-left: auto;
            font-size: 0.85rem;
            color: #a0a0b0;
        }

        .api-notice {
            background: #2d2a1e;
            border-bottom: 1px solid #5c4d1a;
            color: #f0d78c;
            padding: 0.6rem 1rem;
            font-family: Inter, sans-serif;
            font-size: 0.9rem;
        }

        .api-notice a {
            color: #ffe28a;
        }
    </style>
</head>

<body>
    <div class="api-header">
        <?= $presenter->getHeaderLink() ?>
        <span style="color:#555">|</span>
        <span>Projects Monitor &mdash; API Documentation</span>
        <span class="session-status"><?= $presenter->getSessionStatus() ?></span>
    </div>
    <?= $presenter->getNotice() ?>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui-bundle.js"
        integrity="sha384-EYdOaiRwn44zNjrw+Tfs06qYz9BGQVo2f4/pLY5i7VorbjnZNhdplAbTBk8FXHUJ"
        crossorigin="anonymous"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui-standalone-preset.js"
        integrity="sha384-49fpFaVrAWI/qdgl9Vv5E/4NXxRUiJX5vGuLws1NUpTWGtEqzWEx8gHTw2UTehFK"
        crossorigin="anonymous"></script>
    <script>
        window.onload = function () {
            const tryItOutEnabled = <?= $presenter->getTryItOutFlag() ?>;
            const config = {
                // Relative to /api/v1/swagger, so it resolves correctly both in
                // production (/projects-monitor/api/v1/openapi) and locally.
                url: 'openapi',
                dom_id: '#swagger-ui',
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                layout: 'StandaloneLayout',
                deepLinking: true,
                displayRequestDuration: true,
                filter: true,
                // Send the dashboard session cookie with "Try it out" requests.
                withCredentials: true
            };
            if (!tryItOutEnabled) {
                // Without a session every protected call would answer 401, so
                // hide "Try it out" for anonymous visitors.
                config.supportedSubmitMethods = [];
            }
            SwaggerUIBundle(config);
        };
    </script>
</body>

</html>