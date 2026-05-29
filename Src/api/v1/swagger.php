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
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Projects Monitor | API Docs</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.29.1/swagger-ui.css" integrity="sha512-eZpfl9qlKnbDvlJ2brfdx3nhlP1FMsA23w65motxKdYsUcfMcdO2bcLPr7mXhvyzmDZwuzYCJKrl/sEo1ditVQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { margin: 0; background: #1a1a2e; }
        .swagger-ui .topbar { background-color: #16213e; }
        .swagger-ui .topbar .download-url-wrapper { display: none; }
        #swagger-ui { max-width: 1400px; margin: 0 auto; padding: 0 1rem 2rem; }
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
        .api-header a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="api-header">
        <a href="../../index.php">&larr; Dashboard</a>
        <span style="color:#555">|</span>
        <span>Projects Monitor &mdash; API Documentation</span>
    </div>
    <div id="swagger-ui"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.29.1/swagger-ui-bundle.js" integrity="sha512-iDdi6WwSTimAFh1NhPyFZpHJWr16m/PupHztElqQL+gQoQDavUATsP9hcvgs9Yci+EUA//WvZYZsCTDhRDKc3g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        SwaggerUIBundle({
            url: 'openapi.php',
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.SwaggerUIStandalonePreset
            ],
            layout: 'StandaloneLayout',
            defaultModelsExpandDepth: 2,
            defaultModelExpandDepth: 2,
            docExpansion: 'list',
            filter: true,
            tryItOutEnabled: true
        });
    </script>
</body>
</html>
