SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS vault_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    storage_quota_bytes BIGINT UNSIGNED NOT NULL DEFAULT 5368709120,
    storage_used_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    last_login_at DATETIME NULL,
    UNIQUE KEY uq_vault_users_email (email),
    KEY idx_vault_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vault_folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#0d9488',
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_vault_folders_user_parent (user_id, parent_id, is_deleted),
    KEY idx_vault_folders_starred (user_id, is_starred),
    CONSTRAINT fk_vault_folders_user FOREIGN KEY (user_id) REFERENCES vault_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_vault_folders_parent FOREIGN KEY (parent_id) REFERENCES vault_folders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vault_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
    extension VARCHAR(20) NOT NULL DEFAULT '',
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    is_purged TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    current_version INT UNSIGNED NOT NULL DEFAULT 1,
    last_accessed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_vault_files_user_folder (user_id, folder_id, is_deleted, is_purged),
    KEY idx_vault_files_starred (user_id, is_starred),
    KEY idx_vault_files_name (user_id, original_name),
    KEY idx_vault_files_deleted (is_deleted, deleted_at),
    CONSTRAINT fk_vault_files_user FOREIGN KEY (user_id) REFERENCES vault_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_vault_files_folder FOREIGN KEY (folder_id) REFERENCES vault_folders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vault_file_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    mime_type VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_vault_versions (file_id, version_number),
    KEY idx_vault_versions_user (user_id),
    CONSTRAINT fk_vault_versions_file FOREIGN KEY (file_id) REFERENCES vault_files (id) ON DELETE CASCADE,
    CONSTRAINT fk_vault_versions_user FOREIGN KEY (user_id) REFERENCES vault_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vault_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#0d9488',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_vault_tags_user_name (user_id, name),
    CONSTRAINT fk_vault_tags_user FOREIGN KEY (user_id) REFERENCES vault_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vault_file_tags (
    file_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (file_id, tag_id),
    CONSTRAINT fk_vault_file_tags_file FOREIGN KEY (file_id) REFERENCES vault_files (id) ON DELETE CASCADE,
    CONSTRAINT fk_vault_file_tags_tag FOREIGN KEY (tag_id) REFERENCES vault_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vault_shares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    resource_type ENUM('file', 'folder') NOT NULL,
    resource_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_vault_shares_token (token),
    KEY idx_vault_shares_resource (user_id, resource_type, resource_id),
    CONSTRAINT fk_vault_shares_user FOREIGN KEY (user_id) REFERENCES vault_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vault_activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    resource_type VARCHAR(40) NULL,
    resource_id INT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_vault_logs_created (created_at),
    KEY idx_vault_logs_user (user_id),
    KEY idx_vault_logs_action (action),
    CONSTRAINT fk_vault_logs_user FOREIGN KEY (user_id) REFERENCES vault_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vault_settings (
    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
