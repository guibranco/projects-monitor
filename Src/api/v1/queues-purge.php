<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\RabbitMq;

$requestBody = file_get_contents("php://input");
$input = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        "error" => "Invalid JSON provided",
        "details" => json_last_error_msg()
    ]);
    exit;
}

foreach (['host', 'vhost', 'queue'] as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Field '{$field}' is required"]);
        exit;
    }
}

$host = filter_var(urldecode($input['host']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$vhost = filter_var(urldecode($input['vhost']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$queueName = filter_var(urldecode($input['queue']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$rabbitMq = new RabbitMq();
$result = $rabbitMq->purgeQueue($host, $vhost, $queueName);

if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred while purging the queue"]);
    exit;
}

$data = $rabbitMq->getQueueLength();
echo json_encode($data);
