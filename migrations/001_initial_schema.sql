-- ============================================================
-- Migration 001: Initial Database Schema
-- Character Set: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_username (username),
    INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_local VARCHAR(200) DEFAULT NULL,
    description TEXT,
    icon VARCHAR(10) DEFAULT '📚',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    name_local VARCHAR(300) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chapters_subject (subject_id),
    CONSTRAINT fk_chapters_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_option CHAR(1) NOT NULL,
    explanation TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_questions_chapter (chapter_id),
    CONSTRAINT fk_questions_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chapter_id INT NOT NULL,
    score INT DEFAULT 0,
    total_questions INT DEFAULT 20,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    INDEX idx_quiz_sessions_user (user_id),
    INDEX idx_quiz_sessions_chapter (chapter_id),
    CONSTRAINT fk_quiz_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_sessions_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    INDEX idx_quiz_answers_session (session_id),
    INDEX idx_quiz_answers_question (question_id),
    CONSTRAINT fk_quiz_answers_session FOREIGN KEY (session_id) REFERENCES quiz_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_answers_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
