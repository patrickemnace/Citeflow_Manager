CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','employee') NOT NULL DEFAULT 'employee',
    designation VARCHAR(120) DEFAULT '',
    image_path VARCHAR(255) DEFAULT '',
    guide_seen_at DATETIME NULL,
    guide_dismissed_at DATETIME NULL,
    permissions TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    brand_name VARCHAR(190) DEFAULT '',
    website VARCHAR(255) DEFAULT '',
    primary_email VARCHAR(190) DEFAULT '',
    portal_password_hash VARCHAR(255) DEFAULT '',
    portal_last_login_at DATETIME NULL,
    guide_seen_at DATETIME NULL,
    guide_dismissed_at DATETIME NULL,
    primary_phone VARCHAR(60) DEFAULT '',
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_name (name)
);

CREATE TABLE IF NOT EXISTS businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    name VARCHAR(190) NOT NULL,
    logo_path VARCHAR(255) DEFAULT '',
    phone VARCHAR(50) NOT NULL,
    website VARCHAR(255) DEFAULT '',
    gbp_link VARCHAR(255) DEFAULT '',
    email VARCHAR(190) DEFAULT '',
    address_line1 VARCHAR(255) NOT NULL,
    city VARCHAR(120) NOT NULL,
    state VARCHAR(120) NOT NULL,
    postal_code VARCHAR(30) NOT NULL,
    country VARCHAR(120) NOT NULL DEFAULT 'USA',
    category VARCHAR(120) DEFAULT '',
    description TEXT,
    hours_json TEXT,
    in_business_since VARCHAR(100) DEFAULT '',
    social_media TEXT,
    contact_name VARCHAR(190) DEFAULT '',
    categories TEXT,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_business_name (name),
    INDEX idx_business_client (client_id),
    INDEX idx_business_status (status),
    UNIQUE KEY uq_business_name_address (name, address_line1, city, state, postal_code),
    CONSTRAINT fk_business_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_business_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS directories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    logo_path VARCHAR(255) DEFAULT '',
    website VARCHAR(255) NOT NULL,
    submission_url VARCHAR(255) DEFAULT '',
    category VARCHAR(120) DEFAULT '',
    directory_type VARCHAR(120) DEFAULT 'General',
    country VARCHAR(120) DEFAULT 'Global',
    priority_level VARCHAR(30) NOT NULL DEFAULT 'medium',
    pricing_model VARCHAR(20) NOT NULL DEFAULT 'free',
    requires_login TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_directory_name (name),
    UNIQUE KEY uq_directory_website (website)
);

CREATE TABLE IF NOT EXISTS listing_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    directory_id INT NOT NULL,
    assigned_to INT NULL,
    status ENUM('not_started','in_progress','pending_submission','unable_to_submit','live','submitted','rejected','needs_edit') NOT NULL DEFAULT 'not_started',
    nap_status VARCHAR(30) NOT NULL DEFAULT 'correct',
    priority_level VARCHAR(30) NOT NULL DEFAULT 'medium',
    submitted_url VARCHAR(255) DEFAULT '',
    notes TEXT,
    citation_type VARCHAR(120) DEFAULT 'Manual Submission',
    last_action_at TIMESTAMP NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_listing_task (business_id, directory_id),
    INDEX idx_task_status (status),
    INDEX idx_task_assigned_to (assigned_to),
    INDEX idx_task_nap_status (nap_status),
    CONSTRAINT fk_task_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_directory FOREIGN KEY (directory_id) REFERENCES directories(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS client_citation_comments (
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
);

CREATE TABLE IF NOT EXISTS submission_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_task_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proof_task (listing_task_id),
    CONSTRAINT fk_proof_task FOREIGN KEY (listing_task_id) REFERENCES listing_tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_proof_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    kind VARCHAR(50) NOT NULL DEFAULT 'info',
    title VARCHAR(190) NOT NULL,
    message TEXT,
    action_url VARCHAR(255) DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_user (user_id, is_read, created_at),
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_entity (entity_type, entity_id),
    INDEX idx_activity_created_at (created_at),
    CONSTRAINT fk_activity_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_presence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
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
);

CREATE TABLE IF NOT EXISTS client_presence (
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
);
