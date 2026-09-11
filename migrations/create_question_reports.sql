-- Migration: Create question_reports table
CREATE TABLE IF NOT EXISTS question_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    reason VARCHAR(50) NOT NULL, -- 'wrong_answer', 'incomplete', 'irrelevant', 'other'
    details TEXT NULL,
    status ENUM('pending', 'resolved', 'dismissed') NOT NULL DEFAULT 'pending',
    resolved_by INT NULL,
    admin_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    INDEX idx_qr_question_id (question_id),
    INDEX idx_qr_user_id (user_id),
    INDEX idx_qr_status (status),
    CONSTRAINT fk_qr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_qr_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_qr_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
