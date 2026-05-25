<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_client_login();

$client = current_client();
if (!is_array($client)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data = [];
if (is_string($rawBody) && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

$pageTitle = null;
if (is_scalar($data['page_title'] ?? null)) {
    $pageTitle = (string)$data['page_title'];
} elseif (is_scalar($_GET['page_title'] ?? null)) {
    $pageTitle = (string)$_GET['page_title'];
}

$currentPath = null;
if (is_scalar($data['current_path'] ?? null)) {
    $currentPath = (string)$data['current_path'];
} elseif (is_scalar($_GET['current_path'] ?? null)) {
    $currentPath = (string)$_GET['current_path'];
}

update_client_presence($client, $pageTitle, $currentPath);

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
