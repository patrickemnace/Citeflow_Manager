<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function redirect(string $path): void
{
    $baseUrl = rtrim((string)app_config()['base_url'], '/');
    $normalizedPath = '/' . ltrim($path, '/');

    // Keep canonical login route at app root and avoid brittle /index.php redirects.
    if ($normalizedPath === '/index.php') {
        $normalizedPath = '/';
    }

    header('Location: ' . $baseUrl . $normalizedPath);
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $msg = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $msg;
}

function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function public_asset_url(string $relativePath): string
{
    return app_config()['base_url'] . '/' . ltrim($relativePath, '/');
}

function cleanup_unused_uploaded_images(): int
{
    $uploadDir = (string)app_config()['upload_dir'];
    if (!is_dir($uploadDir)) {
        return 0;
    }

    $usedFilenames = [];
    $referenceColumns = [
        ['table' => 'businesses', 'column' => 'logo_path'],
        ['table' => 'directories', 'column' => 'logo_path'],
        ['table' => 'users', 'column' => 'image_path'],
    ];

    foreach ($referenceColumns as $ref) {
        if (!function_exists('table_exists') || !table_exists($ref['table'])) {
            continue;
        }

        try {
            $sql = 'SELECT ' . $ref['column'] . ' AS p FROM ' . $ref['table'] . ' WHERE ' . $ref['column'] . ' IS NOT NULL AND ' . $ref['column'] . ' <> ""';
            $rows = db()->query($sql)->fetchAll();
            foreach ($rows as $row) {
                $path = trim((string)($row['p'] ?? ''));
                if ($path === '' || !str_starts_with($path, 'uploads/')) {
                    continue;
                }

                $name = basename($path);
                if ($name !== '' && $name !== '.' && $name !== '..') {
                    $usedFilenames[$name] = true;
                }
            }
        } catch (Throwable $e) {
            // Cleanup should never break normal requests.
        }
    }

    $removedCount = 0;
    $entries = scandir($uploadDir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '' || $entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
            continue;
        }

        $fullPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($fullPath)) {
            continue;
        }

        if (!isset($usedFilenames[$entry])) {
            if (@unlink($fullPath)) {
                $removedCount++;
            }
        }
    }

    return $removedCount;
}

function schedule_uploaded_images_cleanup(): void
{
    static $scheduled = false;
    if ($scheduled) {
        return;
    }

    $scheduled = true;
    register_shutdown_function(static function (): void {
        cleanup_unused_uploaded_images();
    });
}

function normalize_upload_subdirectory(string $subDirectory): ?string
{
    if ($subDirectory === '') {
        return '';
    }

    $safeSubDir = trim((string)preg_replace('/[^a-z0-9_\/-]+/i', '_', $subDirectory), '/\\');
    if ($safeSubDir === '') {
        return null;
    }

    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $safeSubDir);
}

function ensure_upload_directory(string $directory): bool
{
    if ($directory === '') {
        return false;
    }

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    if (!is_dir($directory)) {
        return false;
    }

    $probe = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.write_test_' . uniqid('', true);
    $written = @file_put_contents($probe, 'ok', LOCK_EX);
    if ($written === false) {
        return false;
    }

    @unlink($probe);
    return true;
}

function resolve_upload_directory(string $subDirectory = ''): ?array
{
    $normalizedSubDir = normalize_upload_subdirectory($subDirectory);
    if ($normalizedSubDir === null) {
        return null;
    }

    $baseCandidates = [];
    $configured = trim((string)(app_config()['upload_dir'] ?? ''));
    if ($configured !== '') {
        $baseCandidates[] = $configured;
        if (!preg_match('/^(?:[a-zA-Z]:[\\\/]|\\\\|\/)/', $configured)) {
            // Relative paths in config are resolved from app directory for portability.
            $baseCandidates[] = __DIR__ . '/' . ltrim($configured, '/\\');
            $baseCandidates[] = __DIR__ . '/../' . ltrim($configured, '/\\');
        }
    }

    // Common deployment layouts: repository root/public/uploads and docroot/uploads.
    $baseCandidates[] = __DIR__ . '/../public/uploads';
    $baseCandidates[] = __DIR__ . '/../uploads';

    $scriptDir = dirname((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($scriptDir !== '' && $scriptDir !== '.') {
        $baseCandidates[] = $scriptDir . '/uploads';
    }

    $docRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRoot !== '') {
        $baseCandidates[] = rtrim($docRoot, '/\\') . '/uploads';
    }

    $normalizedCandidates = [];
    foreach ($baseCandidates as $candidate) {
        $normalized = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$candidate), DIRECTORY_SEPARATOR);
        if ($normalized === '') {
            continue;
        }
        if (!in_array($normalized, $normalizedCandidates, true)) {
            $normalizedCandidates[] = $normalized;
        }
    }

    foreach ($normalizedCandidates as $baseDir) {
        $targetDir = $baseDir;
        if ($normalizedSubDir !== '') {
            $targetDir .= DIRECTORY_SEPARATOR . $normalizedSubDir;
        }

        if (!ensure_upload_directory($targetDir)) {
            continue;
        }

        return [
            'directory' => $targetDir,
            'sub_directory' => $normalizedSubDir,
        ];
    }

    return null;
}

function encode_gd_image_to_bytes(
    GdImage $image,
    string $format,
    int $quality = 82,
    int $compression = 6,
    bool $preserveAlpha = false
): ?string {
    ob_start();

    $ok = false;
    if ($format === 'jpeg') {
        $ok = imagejpeg($image, null, max(15, min(95, $quality)));
    } elseif ($format === 'webp' && function_exists('imagewebp')) {
        $ok = imagewebp($image, null, max(15, min(95, $quality)));
    } elseif ($format === 'png') {
        if ($preserveAlpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
        $ok = imagepng($image, null, max(0, min(9, $compression)));
    } elseif ($format === 'gif') {
        $ok = imagegif($image);
    }

    $bytes = ob_get_clean();
    if (!$ok || !is_string($bytes) || $bytes === '') {
        return null;
    }

    return $bytes;
}

function flatten_image_for_jpeg(GdImage $source): GdImage
{
    $width = imagesx($source);
    $height = imagesy($source);

    $canvas = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

    return $canvas;
}

function optimize_image_binary(string $bytes, int $maxUploadSize, ?string &$error = null): ?array
{
    $error = null;

    if ($bytes === '') {
        $error = 'Invalid uploaded file.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = (string)$finfo->buffer($bytes);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$detectedMime])) {
        $error = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
        return null;
    }

    if (strlen($bytes) <= $maxUploadSize) {
        return [
            'bytes' => $bytes,
            'extension' => $allowed[$detectedMime],
            'mime' => $detectedMime,
        ];
    }

    if (!function_exists('imagecreatefromstring')) {
        $error = 'Image is too large and cannot be optimized on this server.';
        return null;
    }

    $image = @imagecreatefromstring($bytes);
    if (!$image instanceof GdImage) {
        $error = 'Invalid uploaded file.';
        return null;
    }

    $candidates = [];

    if (function_exists('imagewebp')) {
        foreach ([86, 78, 70, 62, 54, 46, 38, 30] as $quality) {
            $encoded = encode_gd_image_to_bytes($image, 'webp', $quality);
            if ($encoded !== null) {
                $candidates[] = ['bytes' => $encoded, 'extension' => 'webp', 'mime' => 'image/webp'];
            }
        }
    }

    $jpegImage = flatten_image_for_jpeg($image);
    foreach ([88, 80, 72, 64, 56, 48, 40, 32, 26] as $quality) {
        $encoded = encode_gd_image_to_bytes($jpegImage, 'jpeg', $quality);
        if ($encoded !== null) {
            $candidates[] = ['bytes' => $encoded, 'extension' => 'jpg', 'mime' => 'image/jpeg'];
        }
    }
    imagedestroy($jpegImage);

    if ($detectedMime === 'image/png') {
        foreach ([9, 8, 7, 6] as $compression) {
            $encoded = encode_gd_image_to_bytes($image, 'png', 82, $compression, true);
            if ($encoded !== null) {
                $candidates[] = ['bytes' => $encoded, 'extension' => 'png', 'mime' => 'image/png'];
            }
        }
    }

    if ($detectedMime === 'image/gif') {
        $encoded = encode_gd_image_to_bytes($image, 'gif');
        if ($encoded !== null) {
            $candidates[] = ['bytes' => $encoded, 'extension' => 'gif', 'mime' => 'image/gif'];
        }
    }

    imagedestroy($image);

    usort($candidates, static function (array $a, array $b): int {
        return strlen((string)$a['bytes']) <=> strlen((string)$b['bytes']);
    });

    foreach ($candidates as $candidate) {
        if (strlen((string)$candidate['bytes']) <= $maxUploadSize) {
            return $candidate;
        }
    }

    $error = 'Image is too large. It could not be optimized to 200KB.';
    return null;
}

function save_image_binary_to_uploads(
    string $bytes,
    string $prefix,
    string $originalName,
    ?string &$error = null,
    string $subDirectory = ''
): ?string {
    $error = null;

    $maxUploadSize = (int)app_config()['max_upload_size'];
    $optimized = optimize_image_binary($bytes, $maxUploadSize, $error);
    if ($optimized === null) {
        return null;
    }

    $resolved = resolve_upload_directory($subDirectory);
    if ($resolved === null) {
        $error = 'Unable to create upload directory.';
        return null;
    }

    $uploadDir = (string)$resolved['directory'];
    $normalizedSubDir = (string)$resolved['sub_directory'];

    $originalBase = strtolower(pathinfo(trim($originalName), PATHINFO_FILENAME));
    $safeBase = trim((string)preg_replace('/[^a-z0-9._-]+/', '_', $originalBase), '._-');
    if ($safeBase === '') {
        $safeBase = strtolower($prefix) . '_image';
    }

    $hash = substr(sha1((string)$optimized['bytes']), 0, 12);
    $filename = $safeBase . '_' . $hash . '.' . (string)$optimized['extension'];
    $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (@file_put_contents($destination, (string)$optimized['bytes'], LOCK_EX) === false) {
        $error = 'Unable to save uploaded image.';
        return null;
    }

    @chmod($destination, 0664);

    if ($normalizedSubDir !== '') {
        return 'uploads/' . str_replace('\\', '/', $normalizedSubDir) . '/' . $filename;
    }

    return 'uploads/' . $filename;
}

function save_uploaded_image_in_uploads(string $fieldName, string $prefix, ?string &$error = null, string $subDirectory = ''): ?string
{
    $error = null;

    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
        return null;
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $error = 'Invalid uploaded file.';
        return null;
    }

    $bytes = @file_get_contents($tmpName);
    if (!is_string($bytes) || $bytes === '') {
        $error = 'Invalid uploaded file.';
        return null;
    }

    return save_image_binary_to_uploads(
        $bytes,
        $prefix,
        trim((string)($file['name'] ?? '')),
        $error,
        $subDirectory
    );
}

function save_uploaded_image(string $fieldName, string $prefix, ?string &$error = null): ?string
{
    return save_uploaded_image_in_uploads($fieldName, $prefix, $error);
}

function delete_uploaded_asset(string $relativePath): void
{
    $path = trim($relativePath);
    if ($path === '' || !str_starts_with($path, 'uploads/')) {
        return;
    }

    $filename = basename($path);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    // Defer cleanup until request shutdown so DB updates in this request are considered.
    schedule_uploaded_images_cleanup();
}

function log_activity(string $entityType, int $entityId, string $action, array|string|null $details = null): void
{
    if ($entityId <= 0 || trim($entityType) === '' || trim($action) === '') {
        return;
    }

    $actorId = null;
    if (function_exists('current_user')) {
        $user = current_user();
        if (is_array($user) && isset($user['id'])) {
            $actorId = (int)$user['id'];
        }
    }

    $detailsText = null;
    if (is_array($details)) {
        $detailsText = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (is_string($details) && trim($details) !== '') {
        $detailsText = trim($details);
    }

    try {
        $sql = 'INSERT INTO activity_logs (actor_id, entity_type, entity_id, action, details) VALUES (?, ?, ?, ?, ?)';
        db()->prepare($sql)->execute([$actorId, $entityType, $entityId, $action, $detailsText]);
    } catch (Throwable $e) {
        // Logging must never break primary operations.
    }
}

function sanitize_tracking_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }

    return mb_substr($value, 0, $maxLength);
}

function normalize_tracking_path(?string $value = null): string
{
    $value = trim((string)($value ?? ($_SERVER['REQUEST_URI'] ?? '/')));
    if ($value === '') {
        return '/';
    }

    $path = (string)parse_url($value, PHP_URL_PATH);
    if ($path === '') {
        $path = '/';
    }

    $query = (string)parse_url($value, PHP_URL_QUERY);
    if ($query === '') {
        return sanitize_tracking_text($path, 255);
    }

    parse_str($query, $queryParams);
    $allowedParams = [];
    foreach (['business_id', 'client_id', 'id'] as $key) {
        $param = $queryParams[$key] ?? null;
        if (is_scalar($param)) {
            $paramValue = (string)$param;
            if ($paramValue !== '' && preg_match('/^\d+$/', $paramValue)) {
                $allowedParams[$key] = $paramValue;
            }
        }
    }

    if (!$allowedParams) {
        return sanitize_tracking_text($path, 255);
    }

    return sanitize_tracking_text($path . '?' . http_build_query($allowedParams), 255);
}

function current_tracking_page_title(?string $pageTitle = null): string
{
    $pageTitle = sanitize_tracking_text((string)$pageTitle, 120);
    if ($pageTitle !== '') {
        return $pageTitle;
    }

    $path = normalize_tracking_path();
    $filename = basename((string)parse_url($path, PHP_URL_PATH));
    if ($filename === '' || $filename === '/') {
        return 'Dashboard';
    }

    return ucwords(str_replace(['.php', '_', '-'], ['', ' ', ' '], $filename));
}

function client_ip_address(): string
{
    return sanitize_tracking_text((string)($_SERVER['REMOTE_ADDR'] ?? ''), 45);
}

function update_user_presence(?array $user = null, ?string $pageTitle = null, ?string $currentPath = null): void
{
    if (!function_exists('table_exists') || !table_exists('user_presence')) {
        return;
    }

    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!is_array($user) || (int)($user['id'] ?? 0) <= 0) {
        return;
    }

    $sql = 'INSERT INTO user_presence (user_id, session_id, session_started_at, current_path, page_title, last_ip, user_agent, last_seen_at)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                session_started_at = IF(session_id <> VALUES(session_id), NOW(), session_started_at),
                session_id = VALUES(session_id),
                current_path = VALUES(current_path),
                page_title = VALUES(page_title),
                last_ip = VALUES(last_ip),
                user_agent = VALUES(user_agent),
                last_seen_at = VALUES(last_seen_at)';

    try {
        db()->prepare($sql)->execute([
            (int)$user['id'],
            sanitize_tracking_text(session_id(), 128),
            normalize_tracking_path($currentPath),
            current_tracking_page_title($pageTitle),
            client_ip_address(),
            sanitize_tracking_text((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
        ]);
    } catch (Throwable $e) {
        // Presence tracking should never break the main request.
    }
}

function update_client_presence(?array $client = null, ?string $pageTitle = null, ?string $currentPath = null): void
{
    if (!function_exists('table_exists') || !table_exists('client_presence')) {
        return;
    }

    $client = $client ?? (function_exists('current_client') ? current_client() : null);
    if (!is_array($client) || (int)($client['id'] ?? 0) <= 0) {
        return;
    }

    $sql = 'INSERT INTO client_presence (client_id, session_id, session_started_at, current_path, page_title, last_ip, user_agent, last_seen_at)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                session_started_at = IF(session_id <> VALUES(session_id), NOW(), session_started_at),
                session_id = VALUES(session_id),
                current_path = VALUES(current_path),
                page_title = VALUES(page_title),
                last_ip = VALUES(last_ip),
                user_agent = VALUES(user_agent),
                last_seen_at = VALUES(last_seen_at)';

    try {
        db()->prepare($sql)->execute([
            (int)$client['id'],
            sanitize_tracking_text(session_id(), 128),
            normalize_tracking_path($currentPath),
            current_tracking_page_title($pageTitle),
            client_ip_address(),
            sanitize_tracking_text((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
        ]);
    } catch (Throwable $e) {
        // Presence tracking should never break the main request.
    }
}

function clear_user_presence(?int $userId = null, ?string $sessionId = null): void
{
    if (!function_exists('table_exists') || !table_exists('user_presence')) {
        return;
    }

    $clauses = [];
    $params = [];

    if ($userId !== null && $userId > 0) {
        $clauses[] = 'user_id = ?';
        $params[] = $userId;
    }

    $sessionId = sanitize_tracking_text((string)$sessionId, 128);
    if ($sessionId !== '') {
        $clauses[] = 'session_id = ?';
        $params[] = $sessionId;
    }

    if (!$clauses) {
        return;
    }

    try {
        db()->prepare('DELETE FROM user_presence WHERE ' . implode(' OR ', $clauses))->execute($params);
    } catch (Throwable $e) {
        // Presence cleanup should never block logout.
    }
}

function clear_client_presence(?int $clientId = null, ?string $sessionId = null): void
{
    if (!function_exists('table_exists') || !table_exists('client_presence')) {
        return;
    }

    $clauses = [];
    $params = [];

    if ($clientId !== null && $clientId > 0) {
        $clauses[] = 'client_id = ?';
        $params[] = $clientId;
    }

    $sessionId = sanitize_tracking_text((string)$sessionId, 128);
    if ($sessionId !== '') {
        $clauses[] = 'session_id = ?';
        $params[] = $sessionId;
    }

    if (!$clauses) {
        return;
    }

    try {
        db()->prepare('DELETE FROM client_presence WHERE ' . implode(' OR ', $clauses))->execute($params);
    } catch (Throwable $e) {
        // Presence cleanup should never block logout.
    }
}

function fetch_live_user_tracking_rows(int $activeWindowSeconds = 120, int $limit = 8): array
{
    if (!function_exists('table_exists') || !table_exists('user_presence')) {
        return [];
    }

    $activeWindowSeconds = max(45, $activeWindowSeconds);
    $limit = max(1, $limit);

    $sql = 'SELECT up.user_id, up.current_path, up.page_title, up.last_seen_at,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, up.last_seen_at, NOW())) AS seconds_since_last_seen,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, COALESCE(up.session_started_at, up.created_at, up.last_seen_at), NOW())) AS seconds_online,
                   u.full_name, u.designation, u.role
            FROM user_presence up
            JOIN users u ON u.id = up.user_id
            WHERE u.is_active = 1 AND up.last_seen_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$activeWindowSeconds . ' SECOND)
            ORDER BY up.last_seen_at DESC, up.user_id DESC
            LIMIT ' . (int)$limit;

    $stmt = db()->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_live_client_tracking_rows(int $activeWindowSeconds = 120, int $limit = 8): array
{
    if (!function_exists('table_exists') || !table_exists('client_presence')) {
        return [];
    }

    $activeWindowSeconds = max(45, $activeWindowSeconds);
    $limit = max(1, $limit);

    $sql = 'SELECT cp.client_id, cp.current_path, cp.page_title, cp.last_seen_at,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, cp.last_seen_at, NOW())) AS seconds_since_last_seen,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, COALESCE(cp.session_started_at, cp.created_at, cp.last_seen_at), NOW())) AS seconds_online,
                   c.name AS client_name, c.primary_email
            FROM client_presence cp
            JOIN clients c ON c.id = cp.client_id
            WHERE c.is_active = 1 AND cp.last_seen_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$activeWindowSeconds . ' SECOND)
            ORDER BY cp.last_seen_at DESC, cp.client_id DESC
            LIMIT ' . (int)$limit;

    $stmt = db()->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function format_relative_seconds(int $seconds): string
{
    $delta = max(0, $seconds);
    if ($delta <= 5) {
        return 'Just now';
    }
    if ($delta < 60) {
        return $delta . 's ago';
    }
    if ($delta < 3600) {
        return (int)floor($delta / 60) . 'm ago';
    }
    if ($delta < 86400) {
        return (int)floor($delta / 3600) . 'h ago';
    }

    return (int)floor($delta / 86400) . 'd ago';
}

function format_duration_seconds(int $seconds): string
{
    $delta = max(0, $seconds);
    if ($delta < 60) {
        return $delta . 's';
    }

    if ($delta < 3600) {
        $minutes = (int)floor($delta / 60);
        $secondsPart = $delta % 60;
        if ($secondsPart === 0) {
            return $minutes . 'm';
        }
        return $minutes . 'm ' . $secondsPart . 's';
    }

    if ($delta < 86400) {
        $hours = (int)floor($delta / 3600);
        $minutesPart = (int)floor(($delta % 3600) / 60);
        if ($minutesPart === 0) {
            return $hours . 'h';
        }
        return $hours . 'h ' . $minutesPart . 'm';
    }

    $days = (int)floor($delta / 86400);
    $hoursPart = (int)floor(($delta % 86400) / 3600);
    if ($hoursPart === 0) {
        return $days . 'd';
    }
    return $days . 'd ' . $hoursPart . 'h';
}

function format_relative_time(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'Just now';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    $delta = time() - $timestamp;
    if ($delta <= 5) {
        return 'Just now';
    }
    if ($delta < 60) {
        return $delta . 's ago';
    }
    if ($delta < 3600) {
        return (int)floor($delta / 60) . 'm ago';
    }
    if ($delta < 86400) {
        return (int)floor($delta / 3600) . 'h ago';
    }

    return (int)floor($delta / 86400) . 'd ago';
}

function status_label(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Unknown';
    }

    return ucwords(str_replace('_', ' ', $value));
}

function parse_csv_lines(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line !== '') {
            $clean[] = $line;
        }
    }

    return $clean;
}

function business_completeness(array $business): array
{
    $checks = [
        'Business Name' => trim((string)($business['name'] ?? '')) !== '',
        'Phone' => trim((string)($business['phone'] ?? '')) !== '',
        'Website' => trim((string)($business['website'] ?? '')) !== '',
        'Email' => trim((string)($business['email'] ?? '')) !== '',
        'Address' => trim((string)($business['address_line1'] ?? '')) !== '',
        'City' => trim((string)($business['city'] ?? '')) !== '',
        'State' => trim((string)($business['state'] ?? '')) !== '',
        'Postal Code' => trim((string)($business['postal_code'] ?? '')) !== '',
        'Country' => trim((string)($business['country'] ?? '')) !== '',
        'Primary Category' => trim((string)($business['category'] ?? $business['categories'] ?? '')) !== '',
        'Description' => trim((string)($business['description'] ?? '')) !== '',
        'Hours' => trim((string)($business['hours_json'] ?? '')) !== '',
        'Contact Name' => trim((string)($business['contact_name'] ?? '')) !== '',
        'Social Media' => trim((string)($business['social_media'] ?? '')) !== '',
        'Business Logo' => trim((string)($business['logo_path'] ?? '')) !== '',
    ];

    $total = count($checks);
    $complete = count(array_filter($checks));
    $missing = [];
    foreach ($checks as $label => $ok) {
        if (!$ok) {
            $missing[] = $label;
        }
    }

    return [
        'score' => $total > 0 ? (int)round(($complete / $total) * 100) : 0,
        'complete_count' => $complete,
        'total_count' => $total,
        'missing' => $missing,
        'checks' => $checks,
    ];
}

function completeness_badge_class(int $score): string
{
    if ($score >= 85) {
        return 'bg-emerald-100 text-emerald-700';
    }
    if ($score >= 60) {
        return 'bg-amber-100 text-amber-700';
    }
    return 'bg-rose-100 text-rose-700';
}

function active_clients(): array
{
    if (!function_exists('table_exists') || !table_exists('clients')) {
        return [];
    }

    return db()->query('SELECT id, name, brand_name FROM clients WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
}

function assignable_users(): array
{
    if (!function_exists('table_exists') || !table_exists('users')) {
        return [];
    }

    return db()->query('SELECT id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name ASC')->fetchAll();
}

function datetime_local_value(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

function normalize_datetime_input(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function create_notification(?int $userId, string $title, string $message, string $actionUrl = '', string $kind = 'info'): void
{
    if (!function_exists('table_exists') || !table_exists('notifications')) {
        return;
    }

    $title = trim($title);
    if ($title === '') {
        return;
    }

    $checkSql = 'SELECT id FROM notifications WHERE ' . ($userId === null ? 'user_id IS NULL' : 'user_id = ?') . ' AND kind = ? AND title = ? AND action_url = ? AND is_read = 0 LIMIT 1';
    $checkStmt = db()->prepare($checkSql);
    $params = $userId === null ? [$kind, $title, $actionUrl] : [$userId, $kind, $title, $actionUrl];
    $checkStmt->execute($params);
    if ($checkStmt->fetch()) {
        return;
    }

    $insert = db()->prepare('INSERT INTO notifications (user_id, kind, title, message, action_url) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$userId, $kind, $title, $message, $actionUrl]);
}

function trim_notifications_bucket(?int $userId, int $maxItems = 10): void
{
    if (!function_exists('table_exists') || !table_exists('notifications')) {
        return;
    }

    if ($maxItems < 1) {
        return;
    }

    $where = $userId === null ? 'user_id IS NULL' : 'user_id = ?';
    $sql = 'SELECT id
            FROM notifications
            WHERE ' . $where . '
            ORDER BY created_at DESC, id DESC
            LIMIT ' . (int)$maxItems . ', 1000000';
    $stmt = db()->prepare($sql);
    $params = $userId === null ? [] : [$userId];
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return;
    }

    $ids = array_values(array_filter(array_map(static fn (array $row): int => (int)($row['id'] ?? 0), $rows)));
    if (!$ids) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $deleteStmt = db()->prepare('DELETE FROM notifications WHERE id IN (' . $placeholders . ')');
    $deleteStmt->execute($ids);
}

function unread_notification_count(?array $user = null): int
{
    if (!function_exists('table_exists') || !table_exists('notifications')) {
        return 0;
    }

    $user = $user ?? current_user();
    if ($user === null) {
        return 0;
    }

    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0');
    $stmt->execute([(int)$user['id']]);
    return (int)($stmt->fetch()['c'] ?? 0);
}

function recent_notifications(?array $user = null, int $limit = 8): array
{
    if (!function_exists('table_exists') || !table_exists('notifications')) {
        return [];
    }

    $user = $user ?? current_user();
    if ($user === null) {
        return [];
    }

    $sql = 'SELECT id, kind, title, message, action_url, is_read, created_at
            FROM notifications
            WHERE user_id = ? OR user_id IS NULL
            ORDER BY created_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int)$limit;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute([(int)$user['id']]);
    return $stmt->fetchAll();
}

function mark_notification_read(int $notificationId, ?array $user = null): void
{
    if (!function_exists('table_exists') || !table_exists('notifications')) {
        return;
    }

    $user = $user ?? current_user();
    if ($user === null || $notificationId <= 0) {
        return;
    }

    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)');
    $stmt->execute([$notificationId, (int)$user['id']]);
}

function notification_business_logo_path(array $notification): string
{
    if (!function_exists('table_exists') || !table_exists('businesses')) {
        return '';
    }

    static $cacheById = [];
    static $cacheByName = [];

    $actionUrl = trim((string)($notification['action_url'] ?? ''));
    $title = trim((string)($notification['title'] ?? ''));
    $message = trim((string)($notification['message'] ?? ''));

    $resolveById = static function (int $businessId) use (&$cacheById): string {
        if ($businessId <= 0) {
            return '';
        }
        if (array_key_exists($businessId, $cacheById)) {
            return (string)$cacheById[$businessId];
        }

        $stmt = db()->prepare('SELECT logo_path FROM businesses WHERE id = ? LIMIT 1');
        $stmt->execute([$businessId]);
        $row = $stmt->fetch();
        $logoPath = trim((string)($row['logo_path'] ?? ''));
        $cacheById[$businessId] = $logoPath;
        return $logoPath;
    };

    $resolveByName = static function (string $name) use (&$cacheByName): string {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $cacheKey = strtolower($name);
        if (array_key_exists($cacheKey, $cacheByName)) {
            return (string)$cacheByName[$cacheKey];
        }

        $stmt = db()->prepare('SELECT logo_path FROM businesses WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        $logoPath = trim((string)($row['logo_path'] ?? ''));

        if ($logoPath === '') {
            $stmt = db()->prepare('SELECT logo_path FROM businesses WHERE name LIKE ? ORDER BY updated_at DESC, id DESC LIMIT 1');
            $stmt->execute([$name . '%']);
            $row = $stmt->fetch();
            $logoPath = trim((string)($row['logo_path'] ?? ''));
        }

        $cacheByName[$cacheKey] = $logoPath;
        return $logoPath;
    };

    if ($actionUrl !== '') {
        $path = strtolower((string)parse_url($actionUrl, PHP_URL_PATH));
        $query = (string)parse_url($actionUrl, PHP_URL_QUERY);
        $queryParams = [];
        parse_str($query, $queryParams);

        $businessId = (int)($queryParams['business_id'] ?? 0);
        if ($businessId > 0) {
            $logo = $resolveById($businessId);
            if ($logo !== '') {
                return $logo;
            }
        }

        $idParam = (int)($queryParams['id'] ?? 0);
        if ($idParam > 0 && (str_contains($path, 'business') || str_contains($path, 'location_manager'))) {
            $logo = $resolveById($idParam);
            if ($logo !== '') {
                return $logo;
            }
        }
    }

    $nameCandidates = [];
    if (preg_match('/new business added:\s*([^\n\r]+)/i', $title, $match)) {
        $nameCandidates[] = trim((string)$match[1]);
    }
    if (preg_match('/business\s*(?:added|updated|removed)?\s*[:\-]\s*([^\n\r]+)/i', $title, $match)) {
        $nameCandidates[] = trim((string)$match[1]);
    }
    if (preg_match('/\b(?:added|updated|removed)\s+([^\(\.\n\r]+)\s*(?:\(|\.|$)/i', $message, $match)) {
        $nameCandidates[] = trim((string)$match[1]);
    }

    foreach ($nameCandidates as $candidate) {
        $candidate = trim((string)preg_replace('/\s+\([^\)]*\)\s*$/', '', $candidate));
        if ($candidate === '') {
            continue;
        }
        $logo = $resolveByName($candidate);
        if ($logo !== '') {
            return $logo;
        }
    }

    return '';
}

function sync_operational_notifications(?array $user = null): void
{
    if (!function_exists('table_exists') || !table_exists('notifications')) {
        return;
    }

    $user = $user ?? current_user();
    if ($user === null) {
        return;
    }
}

function overdue_task_summary(?array $user = null): array
{
    return [];
}

function audit_due_summary(?array $user = null): array
{
    return [];
}
