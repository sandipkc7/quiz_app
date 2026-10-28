-- ============================================================
-- Production Migration Script (Safe & Idempotent)
-- File: /temp/production_migration.sql
-- Feature: Password Reset System (005_create_password_resets.sql)
--
-- Instructions:
--   Option A (Recommended):
--     Run `php migrate.php` from project root on the production server.
--
--   Option B (MySQL CLI):
--     mysql -u your_db_user -p your_db_name < temp/production_migration.sql
--
--   Option C (phpMyAdmin / Adminer):
--     Select your database -> SQL tab -> Paste this file -> Click "Go"
-- ============================================================

-- 1. Create password_resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME DEFAULT NULL,
    INDEX idx_password_resets_token (token_hash),
    INDEX idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Register migration in schema_migrations (if table exists)
SET @has_migrations_table = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'
);

SET @regMigrationSql = IF(@has_migrations_table > 0,
    "INSERT IGNORE INTO schema_migrations (version, applied_at) VALUES ('005_create_password_resets.sql', NOW())",
    "SELECT 1"
);
PREPARE registerMigrationStmt FROM @regMigrationSql;
EXECUTE registerMigrationStmt;
DEALLOCATE PREPARE registerMigrationStmt;

-- 3. Verification check
SELECT 
    'password_resets table successfully verified' AS status,
    COUNT(*) AS total_resets
FROM password_resets;
