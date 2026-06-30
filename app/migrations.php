<?php

declare(strict_types=1);

function is_table_missing_in_engine(Throwable $e): bool
{
    $msg = strtolower($e->getMessage());
    return str_contains($msg, "doesn't exist in engine") || str_contains($msg, 'base table or view not found');
}

function is_tablespace_exists_error(Throwable $e): bool
{
    $msg = strtolower($e->getMessage());
    return str_contains($msg, 'tablespace for table') && str_contains($msg, 'exists');
}

function extract_table_from_tablespace_error(Throwable $e): ?array
{
    $message = $e->getMessage();
    if (!preg_match('/`([^`]+)`\.`([^`]+)`/', $message, $matches)) {
        return null;
    }

    return [
        'database' => $matches[1],
        'table' => $matches[2],
    ];
}

function cleanup_orphan_tablespace_file(string $database, string $table): void
{
    $varStmt = db()->query("SHOW VARIABLES LIKE 'datadir'");
    $varRow = $varStmt->fetch();
    if (!is_array($varRow)) {
        return;
    }

    $datadir = trim((string)($varRow['Value'] ?? ''));
    if ($datadir === '') {
        return;
    }

    $baseDir = rtrim($datadir, "\\/");
    $dbDir = $baseDir . DIRECTORY_SEPARATOR . $database;
    $ibdPath = $dbDir . DIRECTORY_SEPARATOR . $table . '.ibd';
    $cfgPath = $dbDir . DIRECTORY_SEPARATOR . $table . '.cfg';

    if (is_file($ibdPath)) {
        @unlink($ibdPath);
    }

    if (is_file($cfgPath)) {
        @unlink($cfgPath);
    }
}

function table_has_column(string $table, string $column): bool
{
    try {
        $stmt = db()->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        if (is_table_missing_in_engine($e)) {
            return false;
        }
        throw $e;
    }
}

function table_column_varchar_length(string $table, string $column): ?int
{
    try {
        $sql = 'SELECT CHARACTER_MAXIMUM_LENGTH
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND DATA_TYPE = "varchar"
                LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute([$table, $column]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) {
            return null;
        }

        return (int)$value;
    } catch (Throwable $e) {
        return null;
    }
}

function table_exists(string $table): bool
{
    try {
        $stmt = db()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        if (is_table_missing_in_engine($e)) {
            return false;
        }
        throw $e;
    }
}

function table_accessible(string $table): bool
{
    if (!table_exists($table)) {
        return false;
    }

    try {
        db()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        return true;
    } catch (Throwable $e) {
        if (is_table_missing_in_engine($e)) {
            return false;
        }
        throw $e;
    }
}

function ensure_core_schema(): void
{
    $coreTables = ['users', 'clients', 'businesses', 'directories', 'listing_tasks', 'client_citation_comments', 'submission_proofs', 'notifications', 'activity_logs', 'user_presence', 'client_presence'];

    db()->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($coreTables as $table) {
        if (table_exists($table) && !table_accessible($table)) {
            db()->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }
    db()->exec('SET FOREIGN_KEY_CHECKS = 1');

    $schemaPath = __DIR__ . '/../database/schema.sql';
    if (!is_file($schemaPath)) {
        return;
    }

    $schemaSql = (string)file_get_contents($schemaPath);
    if (trim($schemaSql) === '') {
        return;
    }

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $schemaSql) ?: [];
    foreach ($statements as $statement) {
        $sql = trim($statement);
        if ($sql === '') {
            continue;
        }

        try {
            db()->exec($sql);
        } catch (Throwable $e) {
            if (!is_tablespace_exists_error($e)) {
                throw $e;
            }

            $tableInfo = extract_table_from_tablespace_error($e);
            if ($tableInfo === null) {
                throw $e;
            }

            cleanup_orphan_tablespace_file((string)$tableInfo['database'], (string)$tableInfo['table']);
            db()->exec($sql);
        }
    }
}

function ensure_runtime_schema(): void
{
    static $ran = false;

    if ($ran) {
        return;
    }

    ensure_core_schema();

    // Some deployments import an older/partial schema and miss user_presence,
    // which breaks live tracking while the endpoint still returns ok:true.
    if (!table_exists('user_presence')) {
        db()->exec("CREATE TABLE IF NOT EXISTS user_presence (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            session_started_at DATETIME NOT NULL,
            current_path VARCHAR(255) NOT NULL DEFAULT '/',
            page_title VARCHAR(120) NOT NULL DEFAULT 'Dashboard',
            last_ip VARCHAR(45) DEFAULT '',
            user_agent VARCHAR(255) DEFAULT '',
            last_seen_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_presence_user (user_id),
            INDEX idx_user_presence_last_seen (last_seen_at),
            INDEX idx_user_presence_updated_at (updated_at),
            CONSTRAINT fk_user_presence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }

    if (table_exists('user_presence')) {
        if (!table_has_column('user_presence', 'session_id')) {
            db()->exec('ALTER TABLE user_presence ADD COLUMN session_id VARCHAR(128) NOT NULL DEFAULT "" AFTER user_id');
        }
        if (!table_has_column('user_presence', 'session_started_at')) {
            db()->exec('ALTER TABLE user_presence ADD COLUMN session_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER session_id');
            db()->exec('UPDATE user_presence SET session_started_at = COALESCE(created_at, last_seen_at, NOW()) WHERE session_started_at IS NULL OR session_started_at = "0000-00-00 00:00:00"');
        }
        if (!table_has_column('user_presence', 'current_path')) {
            db()->exec('ALTER TABLE user_presence ADD COLUMN current_path VARCHAR(255) NOT NULL DEFAULT "/" AFTER session_id');
        }
        if (!table_has_column('user_presence', 'page_title')) {
            db()->exec('ALTER TABLE user_presence ADD COLUMN page_title VARCHAR(120) NOT NULL DEFAULT "Dashboard" AFTER current_path');
        }
        if (!table_has_column('user_presence', 'last_ip')) {
            db()->exec('ALTER TABLE user_presence ADD COLUMN last_ip VARCHAR(45) DEFAULT "" AFTER page_title');
        }
        if (!table_has_column('user_presence', 'user_agent')) {
            db()->exec('ALTER TABLE user_presence ADD COLUMN user_agent VARCHAR(255) DEFAULT "" AFTER last_ip');
        }
        if (!table_has_column('user_presence', 'last_seen_at')) {
            db()->exec('ALTER TABLE user_presence ADD COLUMN last_seen_at DATETIME NOT NULL AFTER user_agent');
        }

        try {
            db()->exec('ALTER TABLE user_presence ADD UNIQUE KEY uq_user_presence_user (user_id)');
        } catch (Throwable $e) {
            // Unique key may already exist.
        }

        try {
            db()->exec('ALTER TABLE user_presence ADD INDEX idx_user_presence_last_seen (last_seen_at)');
        } catch (Throwable $e) {
            // Index may already exist.
        }

        try {
            db()->exec('ALTER TABLE user_presence ADD INDEX idx_user_presence_updated_at (updated_at)');
        } catch (Throwable $e) {
            // Index may already exist.
        }

        try {
            db()->exec('ALTER TABLE user_presence ADD CONSTRAINT fk_user_presence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
        } catch (Throwable $e) {
            // Constraint may already exist.
        }
    }

    if (!table_exists('client_presence')) {
        db()->exec("CREATE TABLE IF NOT EXISTS client_presence (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            session_started_at DATETIME NOT NULL,
            current_path VARCHAR(255) NOT NULL DEFAULT '/',
            page_title VARCHAR(120) NOT NULL DEFAULT 'Client Portal',
            last_ip VARCHAR(45) DEFAULT '',
            user_agent VARCHAR(255) DEFAULT '',
            last_seen_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_client_presence_client (client_id),
            INDEX idx_client_presence_last_seen (last_seen_at),
            INDEX idx_client_presence_updated_at (updated_at),
            CONSTRAINT fk_client_presence_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        )");
    }

    if (table_exists('client_presence')) {
        if (!table_has_column('client_presence', 'session_id')) {
            db()->exec('ALTER TABLE client_presence ADD COLUMN session_id VARCHAR(128) NOT NULL DEFAULT "" AFTER client_id');
        }
        if (!table_has_column('client_presence', 'session_started_at')) {
            db()->exec('ALTER TABLE client_presence ADD COLUMN session_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER session_id');
            db()->exec('UPDATE client_presence SET session_started_at = COALESCE(created_at, last_seen_at, NOW()) WHERE session_started_at IS NULL OR session_started_at = "0000-00-00 00:00:00"');
        }
        if (!table_has_column('client_presence', 'current_path')) {
            db()->exec('ALTER TABLE client_presence ADD COLUMN current_path VARCHAR(255) NOT NULL DEFAULT "/" AFTER session_id');
        }
        if (!table_has_column('client_presence', 'page_title')) {
            db()->exec('ALTER TABLE client_presence ADD COLUMN page_title VARCHAR(120) NOT NULL DEFAULT "Client Portal" AFTER current_path');
        }
        if (!table_has_column('client_presence', 'last_ip')) {
            db()->exec('ALTER TABLE client_presence ADD COLUMN last_ip VARCHAR(45) DEFAULT "" AFTER page_title');
        }
        if (!table_has_column('client_presence', 'user_agent')) {
            db()->exec('ALTER TABLE client_presence ADD COLUMN user_agent VARCHAR(255) DEFAULT "" AFTER last_ip');
        }
        if (!table_has_column('client_presence', 'last_seen_at')) {
            db()->exec('ALTER TABLE client_presence ADD COLUMN last_seen_at DATETIME NOT NULL AFTER user_agent');
        }

        try {
            db()->exec('ALTER TABLE client_presence ADD UNIQUE KEY uq_client_presence_client (client_id)');
        } catch (Throwable $e) {
            // Unique key may already exist.
        }

        try {
            db()->exec('ALTER TABLE client_presence ADD INDEX idx_client_presence_last_seen (last_seen_at)');
        } catch (Throwable $e) {
            // Index may already exist.
        }

        try {
            db()->exec('ALTER TABLE client_presence ADD INDEX idx_client_presence_updated_at (updated_at)');
        } catch (Throwable $e) {
            // Index may already exist.
        }

        try {
            db()->exec('ALTER TABLE client_presence ADD CONSTRAINT fk_client_presence_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE');
        } catch (Throwable $e) {
            // Constraint may already exist.
        }
    }

    if (!table_exists('businesses')) {
        $ran = true;
        return;
    }

    $businessColumns = [
        'client_id' => 'ALTER TABLE businesses ADD COLUMN client_id INT NULL AFTER id',
        'logo_path' => 'ALTER TABLE businesses ADD COLUMN logo_path VARCHAR(255) DEFAULT "" AFTER name',
        'gbp_link' => 'ALTER TABLE businesses ADD COLUMN gbp_link VARCHAR(255) DEFAULT "" AFTER website',
        'services' => 'ALTER TABLE businesses ADD COLUMN services TEXT NULL AFTER description',
        'login_credentials' => 'ALTER TABLE businesses ADD COLUMN login_credentials TEXT NULL AFTER hours_json',
        'payment_methods' => 'ALTER TABLE businesses ADD COLUMN payment_methods TEXT NULL AFTER login_credentials',
        'in_business_since' => 'ALTER TABLE businesses ADD COLUMN in_business_since VARCHAR(100) NULL AFTER payment_methods',
        'social_media' => 'ALTER TABLE businesses ADD COLUMN social_media TEXT NULL AFTER in_business_since',
        'contact_name' => 'ALTER TABLE businesses ADD COLUMN contact_name VARCHAR(190) NULL AFTER social_media',
        'categories' => 'ALTER TABLE businesses ADD COLUMN categories TEXT NULL AFTER contact_name',
        'status'     => "ALTER TABLE businesses ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER categories",
    ];

    foreach ($businessColumns as $column => $sql) {
        if (!table_has_column('businesses', $column)) {
            db()->exec($sql);
        }
    }

    try {
        db()->exec('ALTER TABLE businesses ADD INDEX idx_business_client (client_id)');
    } catch (Throwable $e) {
        // Index may already exist.
    }

    try {
        db()->exec('ALTER TABLE businesses ADD CONSTRAINT uq_business_name_address UNIQUE (name, address_line1, city, state, postal_code)');
    } catch (Throwable $e) {
        // Unique constraint may already exist or legacy duplicates may prevent it.
    }

    try {
        db()->exec('ALTER TABLE businesses ADD CONSTRAINT fk_business_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL');
    } catch (Throwable $e) {
        // Constraint may already exist.
    }

    if (table_exists('users')) {
        if (!table_has_column('users', 'permissions')) {
            db()->exec('ALTER TABLE users ADD COLUMN permissions TEXT NULL AFTER role');
        }
        if (!table_has_column('users', 'guide_seen_at')) {
            db()->exec('ALTER TABLE users ADD COLUMN guide_seen_at DATETIME NULL AFTER image_path');
        }
        if (!table_has_column('users', 'guide_dismissed_at')) {
            db()->exec('ALTER TABLE users ADD COLUMN guide_dismissed_at DATETIME NULL AFTER guide_seen_at');
        }

        db()->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','employee','user') NOT NULL DEFAULT 'employee'");
        db()->exec("UPDATE users SET role = 'employee' WHERE role = 'user'");
        db()->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','employee') NOT NULL DEFAULT 'employee'");
    }

    if (table_exists('clients')) {
        if (!table_has_column('clients', 'guide_seen_at')) {
            db()->exec('ALTER TABLE clients ADD COLUMN guide_seen_at DATETIME NULL AFTER portal_last_login_at');
        }
        if (!table_has_column('clients', 'guide_dismissed_at')) {
            db()->exec('ALTER TABLE clients ADD COLUMN guide_dismissed_at DATETIME NULL AFTER guide_seen_at');
        }
    }

    if (!table_exists('client_citation_comments')) {
        db()->exec("CREATE TABLE IF NOT EXISTS client_citation_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            listing_task_id INT NOT NULL,
            client_id INT NOT NULL,
            comment_text TEXT NOT NULL,
            resolution_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            resolved_by INT NULL,
            resolved_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_client_citation_comments_task (listing_task_id, created_at),
            INDEX idx_client_citation_comments_client (client_id, created_at),
            INDEX idx_client_citation_comments_status (resolution_status, resolved_at),
            CONSTRAINT fk_client_citation_comments_task FOREIGN KEY (listing_task_id) REFERENCES listing_tasks(id) ON DELETE CASCADE,
            CONSTRAINT fk_client_citation_comments_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            CONSTRAINT fk_client_citation_comments_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
        )");
    }

    if (table_exists('client_citation_comments')) {
        if (!table_has_column('client_citation_comments', 'listing_task_id')) {
            db()->exec('ALTER TABLE client_citation_comments ADD COLUMN listing_task_id INT NOT NULL AFTER id');
        }
        if (!table_has_column('client_citation_comments', 'client_id')) {
            db()->exec('ALTER TABLE client_citation_comments ADD COLUMN client_id INT NOT NULL AFTER listing_task_id');
        }
        if (!table_has_column('client_citation_comments', 'comment_text')) {
            db()->exec('ALTER TABLE client_citation_comments ADD COLUMN comment_text TEXT NOT NULL AFTER client_id');
        }
        if (!table_has_column('client_citation_comments', 'resolution_status')) {
            db()->exec('ALTER TABLE client_citation_comments ADD COLUMN resolution_status VARCHAR(20) NOT NULL DEFAULT "pending" AFTER comment_text');
        }
        if (!table_has_column('client_citation_comments', 'resolved_by')) {
            db()->exec('ALTER TABLE client_citation_comments ADD COLUMN resolved_by INT NULL AFTER resolution_status');
        }
        if (!table_has_column('client_citation_comments', 'resolved_at')) {
            db()->exec('ALTER TABLE client_citation_comments ADD COLUMN resolved_at DATETIME NULL AFTER resolved_by');
        }
        if (!table_has_column('client_citation_comments', 'updated_at')) {
            db()->exec('ALTER TABLE client_citation_comments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
        }

        try {
            db()->exec('ALTER TABLE client_citation_comments ADD INDEX idx_client_citation_comments_task (listing_task_id, created_at)');
        } catch (Throwable $e) {
            // Index may already exist.
        }

        try {
            db()->exec('ALTER TABLE client_citation_comments ADD INDEX idx_client_citation_comments_client (client_id, created_at)');
        } catch (Throwable $e) {
            // Index may already exist.
        }

        try {
            db()->exec('ALTER TABLE client_citation_comments ADD INDEX idx_client_citation_comments_status (resolution_status, resolved_at)');
        } catch (Throwable $e) {
            // Index may already exist.
        }

        try {
            db()->exec('ALTER TABLE client_citation_comments ADD CONSTRAINT fk_client_citation_comments_task FOREIGN KEY (listing_task_id) REFERENCES listing_tasks(id) ON DELETE CASCADE');
        } catch (Throwable $e) {
            // Constraint may already exist.
        }

        try {
            db()->exec('ALTER TABLE client_citation_comments ADD CONSTRAINT fk_client_citation_comments_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE');
        } catch (Throwable $e) {
            // Constraint may already exist.
        }

        try {
            db()->exec('ALTER TABLE client_citation_comments ADD CONSTRAINT fk_client_citation_comments_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL');
        } catch (Throwable $e) {
            // Constraint may already exist.
        }
    }

    $taskColumns = [
        'citation_type' => 'ALTER TABLE listing_tasks ADD COLUMN citation_type VARCHAR(120) DEFAULT "Manual Submission" AFTER notes',
        'priority_level' => 'ALTER TABLE listing_tasks ADD COLUMN priority_level VARCHAR(30) NOT NULL DEFAULT "medium" AFTER status',
        'nap_status' => 'ALTER TABLE listing_tasks ADD COLUMN nap_status VARCHAR(30) NOT NULL DEFAULT "correct" AFTER status',
    ];

    foreach ($taskColumns as $column => $sql) {
        if (!table_has_column('listing_tasks', $column)) {
            db()->exec($sql);
        }
    }

    if (table_has_column('listing_tasks', 'submitted_url')) {
        $submittedUrlLength = table_column_varchar_length('listing_tasks', 'submitted_url');
        if ($submittedUrlLength !== null && $submittedUrlLength < 2048) {
            db()->exec('ALTER TABLE listing_tasks MODIFY COLUMN submitted_url VARCHAR(2048) DEFAULT ""');
        }
    }

    try {
        db()->exec("ALTER TABLE listing_tasks MODIFY COLUMN status ENUM('not_started','in_progress','pending_submission','unable_to_submit','live','submitted','rejected','needs_edit') NOT NULL DEFAULT 'not_started'");
    } catch (Throwable $e) {
        // Column definition may already match.
    }

    try {
        db()->exec("UPDATE listing_tasks SET status = 'pending_submission' WHERE status = 'submitted'");
    } catch (Throwable $e) {
        // Keep runtime safe if update cannot be applied.
    }

    if (table_exists('directories')) {
        $directoryColumns = [
            'logo_path' => 'ALTER TABLE directories ADD COLUMN logo_path VARCHAR(255) DEFAULT "" AFTER name',
            'directory_type' => 'ALTER TABLE directories ADD COLUMN directory_type VARCHAR(120) DEFAULT "General" AFTER category',
            'priority_level' => 'ALTER TABLE directories ADD COLUMN priority_level VARCHAR(30) NOT NULL DEFAULT "medium" AFTER country',
            'pricing_model' => 'ALTER TABLE directories ADD COLUMN pricing_model VARCHAR(20) NOT NULL DEFAULT "free" AFTER priority_level',
            'average_approval_days' => 'ALTER TABLE directories ADD COLUMN average_approval_days INT NOT NULL DEFAULT 7 AFTER pricing_model',
        ];

        foreach ($directoryColumns as $column => $sql) {
            if (!table_has_column('directories', $column)) {
                db()->exec($sql);
            }
        }

        if (table_has_column('directories', 'submission_url')) {
            $submissionUrlLength = table_column_varchar_length('directories', 'submission_url');
            if ($submissionUrlLength !== null && $submissionUrlLength < 2048) {
                db()->exec('ALTER TABLE directories MODIFY COLUMN submission_url VARCHAR(2048) DEFAULT ""');
            }
        }

        try {
            db()->exec('ALTER TABLE directories ADD CONSTRAINT uq_directory_website UNIQUE (website)');
        } catch (Throwable $e) {
            // Unique constraint may already exist or legacy duplicates may prevent it.
        }

        if (table_has_column('directories', 'autofill_mapping')) {
            db()->exec('ALTER TABLE directories DROP COLUMN autofill_mapping');
        }
    }

    foreach (['autofill', 'autofill_packs', 'autofill_mappings'] as $legacyTable) {
        if (table_exists($legacyTable)) {
            db()->exec('DROP TABLE IF EXISTS `' . $legacyTable . '`');
        }
    }

    if (table_exists('clients')) {
        $clientColumns = [
            'brand_name' => 'ALTER TABLE clients ADD COLUMN brand_name VARCHAR(190) DEFAULT "" AFTER name',
            'website' => 'ALTER TABLE clients ADD COLUMN website VARCHAR(255) DEFAULT "" AFTER brand_name',
            'primary_email' => 'ALTER TABLE clients ADD COLUMN primary_email VARCHAR(190) DEFAULT "" AFTER website',
            'portal_password_hash' => 'ALTER TABLE clients ADD COLUMN portal_password_hash VARCHAR(255) DEFAULT "" AFTER primary_email',
            'portal_last_login_at' => 'ALTER TABLE clients ADD COLUMN portal_last_login_at DATETIME NULL AFTER portal_password_hash',
            'primary_phone' => 'ALTER TABLE clients ADD COLUMN primary_phone VARCHAR(60) DEFAULT "" AFTER primary_email',
            'notes' => 'ALTER TABLE clients ADD COLUMN notes TEXT NULL AFTER primary_phone',
            'is_active' => 'ALTER TABLE clients ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER notes',
            'updated_at' => 'ALTER TABLE clients ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];

        foreach ($clientColumns as $column => $sql) {
            if (!table_has_column('clients', $column)) {
                db()->exec($sql);
            }
        }
    }

    $ran = true;
}
