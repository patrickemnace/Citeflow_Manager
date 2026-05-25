<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $stmt = db()->prepare('SELECT id, full_name, email, role, permissions, image_path, designation, guide_seen_at, guide_dismissed_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function current_client(): ?array
{
    if (!isset($_SESSION['client_id'])) {
        return null;
    }

    static $client = null;
    if ($client !== null) {
        return $client;
    }

    $stmt = db()->prepare('SELECT id, name, brand_name, primary_email, is_active, guide_seen_at, guide_dismissed_at FROM clients WHERE id = ?');
    $stmt->execute([$_SESSION['client_id']]);
    $row = $stmt->fetch() ?: null;
    if ($row !== null && (int)($row['is_active'] ?? 0) !== 1) {
        unset($_SESSION['client_id']);
        return null;
    }

    $client = $row;
    return $client;
}

function login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    if (!password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['id'];
    return true;
}

function login_client(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, portal_password_hash FROM clients WHERE primary_email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    $hash = trim((string)($row['portal_password_hash'] ?? ''));
    if ($hash === '') {
        return false;
    }

    if (!password_verify($password, $hash)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['client_id'] = (int)$row['id'];
    db()->prepare('UPDATE clients SET portal_last_login_at = NOW() WHERE id = ?')->execute([(int)$row['id']]);
    return true;
}

function logout(): void
{
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    clear_user_presence($userId, session_id());
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function logout_client(): void
{
    $clientId = isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : null;
    clear_client_presence($clientId, session_id());
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (!current_user()) {
        redirect('/');
    }
}

function require_client_login(): void
{
    if (!current_client()) {
        redirect('/client_portal.php');
    }
}