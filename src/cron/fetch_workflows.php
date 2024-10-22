<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Services\WorkflowService;

$db = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
$githubToken = 'your_github_token';

$workflowService = new WorkflowService($db, $githubToken);
$workflowService->fetchWorkflowsAndStore();
