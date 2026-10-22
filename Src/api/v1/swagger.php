<?php
require_once '../../session.php';

/*
 * The API reference is public: anyone can read the spec and browse the
 * endpoints. Only "Try it out" needs an authenticated dashboard session,
 * because the protected endpoints are guarded by session_validator.php.
 *
 * Swagger UI runs on the same origin as the API, so the browser attaches the
 * PHPSESSID cookie to "Try it out" requests by itself whenever the visitor is
 * logged in to the dashboard. Nothing has to be pasted into the Authorize
 * dialog (browsers do not let JavaScript set the Cookie header, so a cookie
 * entered there would never be sent anyway). For anonymous visitors we hide
 * "Try it out" instead of letting every call fail with 401.
 */
define('SWAGGER_SESSION_TIMEOUT', 1800);

$isLocalDevelopment = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost:8000';
$hasActiveSession = isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])
    && isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) <= SWAGGER_SESSION_TIMEOUT;

if ($hasActiveSession) {
    $_SESSION['last_activity'] = time();
}

// session_validator.php auto-authenticates API calls on localhost:8000, so
// "Try it out" works there without logging in.
$canTryItOut = $hasActiveSession || $isLocalDevelopment;
$username = $hasActiveSession ? ($_SESSION['username'] ?? '') : '';

session_write_close();

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$version = '0.0.0-dev';
$versionFile = __DIR__ . '/../../version.txt';
if (file_exists($versionFile)) {
    $v = trim(file_get_contents($versionFile));
    if ($v !== '') {
        $version = $v;
    }
}
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
        <?php if ($canTryItOut): ?>
            <a href="../../index.php">&larr; Dashboard</a>
        <?php else: ?>
            <a href="../../login.php">&larr; Log in</a>
        <?php endif; ?>
        <span style="color:#555">|</span>
        <span>Projects Monitor &mdash; API Documentation</span>
        <span class="session-status">
            <?php if ($username !== ''): ?>
                Signed in as <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php elseif ($canTryItOut): ?>
                Local development &mdash; Try it out enabled
            <?php else: ?>
                Read-only
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$canTryItOut): ?>
        <div class="api-notice">
            You are browsing the public API reference. <a href="../../login.php">Log in</a> to the dashboard to
            enable <em>Try it out</em> &mdash; requests are sent with your dashboard session cookie, so no token
            needs to be entered here.
        </div>
    <?php endif; ?>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui-bundle.js"
        integrity="sha384-EYdOaiRwn44zNjrw+Tfs06qYz9BGQVo2f4/pLY5i7VorbjnZNhdplAbTBk8FXHUJ"
        crossorigin="anonymous"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui-standalone-preset.js"
        integrity="sha384-49fpFaVrAWI/qdgl9Vv5E/4NXxRUiJX5vGuLws1NUpTWGtEqzWEx8gHTw2UTehFK"
        crossorigin="anonymous"></script>
    <script>
        window.onload = function () {
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
            <?php if (!$canTryItOut): ?>
            // Without a session every protected call would answer 401, so hide
            // "Try it out" for anonymous visitors.
            config.supportedSubmitMethods = [];
            <?php endif; ?>
            SwaggerUIBundle(config);
        };
    </script>
</body>

</html>