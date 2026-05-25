<?php

declare(strict_types=1);

// ============================================================
//  PRODUCTION CONFIG — citeflowmanager.free.nf (InfinityFree)
//  Upload this file to: public_html/app/config.php
//  Replace the current app/config.php on the server with this.
// ============================================================

return [
    'db_host'         => 'sql310.infinityfree.com',
    'db_port'         => 3306,
    'db_name'         => 'if0_41687331_citeflow',
    'db_user'         => 'if0_41687331',
    'db_pass'         => 'YOUR_DB_PASSWORD_HERE',   // ← fill in before uploading
    'app_name'        => 'CiteFlow Manager',
    'timezone'        => 'Asia/Manila',
    'base_url'        => '',                         // empty = files are in public_html root
    'upload_dir'      => __DIR__ . '/../public/uploads',
    'max_upload_size' => 200 * 1024,
];
