<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

logout();
set_flash('ok', 'You have been logged out.');
redirect('/');
