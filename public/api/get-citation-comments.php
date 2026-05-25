<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../app/bootstrap.php';

    // Check if client is logged in
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $clientId = (int)($_SESSION['client_id'] ?? 0);
    if ($clientId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $citationId = (int)($_GET['citation_id'] ?? 0);

    if ($citationId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid citation ID'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Verify this citation belongs to the client
    $verifyStmt = db()->prepare('SELECT lt.id
                                 FROM listing_tasks lt
                                 INNER JOIN businesses b ON b.id = lt.business_id
                                 WHERE lt.id = ? AND b.client_id = ?
                                 LIMIT 1');
    $verifyStmt->execute([$citationId, $clientId]);
    if (!$verifyStmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Citation not found for your account'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Fetch all comments for this citation
    $stmt = db()->prepare('SELECT id,
                                  comment_text,
                                  created_at,
                                  resolution_status,
                                  resolved_at,
                                  resolved_by
                           FROM client_citation_comments
                           WHERE listing_task_id = ? AND client_id = ?
                           ORDER BY created_at ASC');
    $stmt->execute([$citationId, $clientId]);
    $comments = $stmt->fetchAll() ?: [];

    http_response_code(200);
    echo json_encode($comments, JSON_UNESCAPED_SLASHES);
    exit;
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_SLASHES);
    exit;
}
