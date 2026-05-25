<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$user = current_user();
$client = current_client();

if ($user === null && $client === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = [];
if (is_string($rawBody) && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$action = strtolower(trim((string)($payload['action'] ?? 'seen')));
if ($action !== 'seen' && $action !== 'dismiss') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid action'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($user !== null) {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user account');
        }

        if ($action === 'dismiss') {
            db()->prepare('UPDATE users SET guide_seen_at = COALESCE(guide_seen_at, NOW()), guide_dismissed_at = COALESCE(guide_dismissed_at, NOW()) WHERE id = ?')->execute([$userId]);
        } else {
            db()->prepare('UPDATE users SET guide_seen_at = COALESCE(guide_seen_at, NOW()) WHERE id = ?')->execute([$userId]);
        }
    } elseif ($client !== null) {
        $clientId = (int)($client['id'] ?? 0);
        if ($clientId <= 0) {
            throw new RuntimeException('Invalid client account');
        }

        if ($action === 'dismiss') {
            db()->prepare('UPDATE clients SET guide_seen_at = COALESCE(guide_seen_at, NOW()), guide_dismissed_at = COALESCE(guide_dismissed_at, NOW()) WHERE id = ?')->execute([$clientId]);
        } else {
            db()->prepare('UPDATE clients SET guide_seen_at = COALESCE(guide_seen_at, NOW()) WHERE id = ?')->execute([$clientId]);
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to save guide state'], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['ok' => true, 'action' => $action], JSON_UNESCAPED_SLASHES);
