-- ============================================================
-- Migration 002: Add Case Studies Table and Link to Questions
-- ============================================================

CREATE TABLE IF NOT EXISTS case_studies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    passage_text LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_case_studies_chapter (chapter_id),
    CONSTRAINT fk_case_studies_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add case_study_id to questions if not exists
SET @dbname = DATABASE();
SET @tablename = "questions";
SET @columnname = "case_study_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE questions ADD COLUMN case_study_id INT DEFAULT NULL AFTER chapter_id, ADD CONSTRAINT fk_questions_case_study FOREIGN KEY (case_study_id) REFERENCES case_studies(id) ON DELETE SET NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
