-- ============================================================
-- Migration 004: Performance Indexes for Question Progression
-- Safe for MySQL / MariaDB (Idempotent)
-- ============================================================

-- 1. Index on quiz_sessions (user_id, chapter_id) for fast user session lookup
SET @dbname = DATABASE();
SET @tablename = "quiz_sessions";
SET @indexname = "idx_qs_user_chapter";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_qs_user_chapter ON quiz_sessions (user_id, chapter_id)"
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- 2. Index on quiz_answers (session_id, question_id, is_correct) for fast answer history lookup
SET @tablename = "quiz_answers";
SET @indexname = "idx_qa_session_q_correct";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_qa_session_q_correct ON quiz_answers (session_id, question_id, is_correct)"
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- 3. Index on questions (chapter_id, case_study_id)
SET @tablename = "questions";
SET @indexname = "idx_q_chapter_cs";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_q_chapter_cs ON questions (chapter_id, case_study_id)"
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;
