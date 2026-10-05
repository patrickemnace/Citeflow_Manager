<?php

declare(strict_types=1);

// Copy this file to app/config.php on each environment (local, staging,
// production) and fill in real values. app/config.php is gitignored, so
// it's created once per server and a `git pull` never touches it again.

return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'app_name' => 'CiteFlow Manager',
    'timezone' => 'Asia/Manila',
    'base_url' => '',
    'upload_dir' => __DIR__ . '/../public/uploads',
    'max_upload_size' => 200 * 1024,
];
