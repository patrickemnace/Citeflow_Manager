<?php

declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
	'lifetime' => 0,
	'path' => '/',
	'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
	'httponly' => true,
	'samesite' => 'Lax',
]);

session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/helpers.php';

$timezone = (string)(app_config()['timezone'] ?? 'Asia/Manila');
date_default_timezone_set($timezone);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/layout.php';

ensure_runtime_schema();
