<?php
/*
 * POST /api/v1/attention/state: snoozes, acknowledges or clears an attention
 * item, by fingerprint. Body: {"fingerprint": "...", "action": "snooze",
 * "minutes": 60}, {"fingerprint": "...", "action": "acknowledge", "since":
 * "<the item's since>"} or {"fingerprint": "...", "action": "clear"}.
 */

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStateRequest;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendErrorResponse("Method not allowed", 405);
}

$request = new AttentionStateRequest(json_decode((string) file_get_contents("php://input"), true));
$error   = $request->validate();
if ($error !== null) {
    sendErrorResponse($error, 400);
}

try {
    $store = new AttentionStore();
} catch (Throwable $exception) {
    sendErrorResponse("Attention store unavailable", 503);
}

if ($request->apply($store, (int) $userId, time()) === false) {
    sendErrorResponse("Could not save the attention state", 500);
}

echo json_encode($request->toArray());
