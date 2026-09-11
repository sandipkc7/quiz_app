-- ============================================================
-- Seed Data: Default Admin, Sample Subjects, Chapters, and Questions
-- Run with: php migrate.php --seed
-- ============================================================

-- Default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, role)
SELECT 'admin', 'admin@quizapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin' OR email = 'admin@quizapp.com');

-- Sample Subjects
INSERT INTO subjects (id, name, name_local, description, icon) VALUES
(1, 'Science', 'विज्ञान', 'Explore the wonders of science — physics, chemistry, biology and more.', '🔬'),
(2, 'Mathematics', 'गणित', 'Sharpen your math skills — algebra, geometry, arithmetic and beyond.', '📐'),
(3, 'General Knowledge', 'सामान्य ज्ञान', 'Test your awareness of the world — history, geography, current affairs.', '🌍'),
(4, 'Computer Science', 'कम्प्युटर विज्ञान', 'Dive into computing — programming, networks, databases and more.', '💻')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Chapters for Science
INSERT INTO chapters (id, subject_id, name, name_local, sort_order) VALUES
(1, 1, 'Physics Basics', 'भौतिकशास्त्रको आधारभूत', 1),
(2, 1, 'Chemistry Fundamentals', 'रसायनशास्त्रको आधारभूत', 2),
(3, 1, 'Biology Essentials', 'जीवविज्ञानको आवश्यक', 3)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Chapters for Mathematics
INSERT INTO chapters (id, subject_id, name, name_local, sort_order) VALUES
(4, 2, 'Arithmetic', 'अंकगणित', 1),
(5, 2, 'Algebra', 'बीजगणित', 2),
(6, 2, 'Geometry', 'ज्यामिति', 3)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Chapters for General Knowledge
INSERT INTO chapters (id, subject_id, name, name_local, sort_order) VALUES
(7, 3, 'World Geography', 'विश्व भूगोल', 1),
(8, 3, 'History', 'इतिहास', 2)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Chapters for Computer Science
INSERT INTO chapters (id, subject_id, name, name_local, sort_order) VALUES
(9, 4, 'Programming Basics', 'प्रोग्रामिङको आधारभूत', 1),
(10, 4, 'Networking', 'नेटवर्किङ', 2)
ON DUPLICATE KEY UPDATE name=VALUES(name);
