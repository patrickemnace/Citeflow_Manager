<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['view'] ?? '') === 'active') {
    if (!is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => 'Forbidden']);
        exit;
    }

    $limit = max(1, min(24, (int)($_GET['limit'] ?? 8)));
    $rows = fetch_live_user_tracking_rows(90, $limit);
    $payload = [];
    foreach ($rows as $row) {
        $payload[] = [
            'user_id' => (int)($row['user_id'] ?? 0),
            'full_name' => (string)($row['full_name'] ?? ''),
            'designation' => (string)($row['designation'] ?? ''),
            'role_label' => ucfirst((string)($row['role'] ?? 'user')),
            'page_title' => (string)($row['page_title'] ?? 'Dashboard'),
            'current_path' => (string)($row['current_path'] ?? '/'),
            'last_seen_at' => (string)($row['last_seen_at'] ?? ''),
            'relative_last_seen' => format_relative_seconds((int)($row['seconds_since_last_seen'] ?? 0)),
            'relative_online_for' => format_duration_seconds((int)($row['seconds_online'] ?? 0)),
        ];
    }

    $clientRows = fetch_live_client_tracking_rows(90, $limit);
    $clientPayload = [];
    foreach ($clientRows as $row) {
        $clientPayload[] = [
            'client_id' => (int)($row['client_id'] ?? 0),
            'client_name' => (string)($row['client_name'] ?? ''),
            'primary_email' => (string)($row['primary_email'] ?? ''),
            'page_title' => (string)($row['page_title'] ?? 'Client Portal'),
            'current_path' => (string)($row['current_path'] ?? '/'),
            'last_seen_at' => (string)($row['last_seen_at'] ?? ''),
            'relative_last_seen' => format_relative_seconds((int)($row['seconds_since_last_seen'] ?? 0)),
            'relative_online_for' => format_duration_seconds((int)($row['seconds_online'] ?? 0)),
        ];
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'users' => $payload,
        'clients' => $clientPayload,
        'generated_at' => date('c'),
        'generated_at_label' => 'Updated ' . date('g:i:s A'),
    ], JSON_UNESCAPED_SLASHES);
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

update_user_presence($user, $pageTitle, $currentPath);

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);