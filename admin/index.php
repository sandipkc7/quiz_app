<?php
/**
 * Admin Dashboard
 */
$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$current_user = current_user();

$db = getDB();

// Stats
$stats = [
    'users'     => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'subjects'  => $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
    'chapters'  => $db->query("SELECT COUNT(*) FROM chapters")->fetchColumn(),
    'questions' => $db->query("SELECT COUNT(*) FROM questions")->fetchColumn(),
    'quizzes'   => $db->query("SELECT COUNT(*) FROM quiz_sessions WHERE completed_at IS NOT NULL")->fetchColumn(),
];

// Recent quizzes
$recent = $db->query("
    SELECT qs.*, u.username, c.name AS chapter_name, s.name AS subject_name
    FROM quiz_sessions qs
    JOIN users u ON u.id = qs.user_id
    JOIN chapters c ON c.id = qs.chapter_id
    JOIN subjects s ON s.id = c.subject_id
    WHERE qs.completed_at IS NOT NULL
    ORDER BY qs.completed_at DESC
    LIMIT 10
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<a href="<?= BASE_URL ?>/dashboard.php" class="back-link">← Back to Dashboard</a>

<div class="page-header">
    <h1 class="page-title">⚙️ Admin Panel</h1>
    <p class="page-subtitle">Manage subjects, chapters, and questions</p>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-card">
        <span class="stat-value"><?= $stats['users'] ?></span>
        <span class="stat-label">Users</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['subjects'] ?></span>
        <span class="stat-label">Subjects</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['questions'] ?></span>
        <span class="stat-label">Questions</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['quizzes'] ?></span>
        <span class="stat-label">Quizzes Taken</span>
    </div>
</div>

<!-- Quick Actions -->
<div class="card-grid mb-xl">
    <a href="<?= BASE_URL ?>/admin/manage_subjects.php" class="card">
        <span class="card-icon">📚</span>
        <h2 class="card-title">Manage Subjects & Chapters</h2>
        <p class="card-description">Add, edit, or remove subjects and their chapters.</p>
    </a>
    <a href="<?= BASE_URL ?>/admin/manage_questions.php" class="card">
        <span class="card-icon">❓</span>
        <h2 class="card-title">Manage Questions</h2>
        <p class="card-description">Add, edit, or remove quiz questions for any chapter.</p>
    </a>
    <a href="<?= BASE_URL ?>/admin/import_questions.php" class="card">
        <span class="card-icon">🤖</span>
        <h2 class="card-title">AI & Bulk Import</h2>
        <p class="card-description">Import AI-generated JSON or CSV questions directly into chapters.</p>
    </a>
</div>

<!-- Recent Quizzes -->
<h2 style="font-size: var(--font-size-xl); font-weight: 700; margin-bottom: var(--space-lg);">Recent Quiz Activity</h2>

<?php if (empty($recent)): ?>
    <div class="empty-state">
        <p class="empty-state-text">No quizzes taken yet.</p>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Subject</th>
                    <th>Chapter</th>
                    <th>Score</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $quiz): ?>
                <tr>
                    <td style="color: var(--text-primary); font-weight: 500;"><?= e($quiz['username']) ?></td>
                    <td><?= e($quiz['subject_name']) ?></td>
                    <td><?= e($quiz['chapter_name']) ?></td>
                    <td>
                        <span class="card-badge"><?= $quiz['score'] ?>/<?= $quiz['total_questions'] ?></span>
                    </td>
                    <td><?= date('M j, Y H:i', strtotime($quiz['completed_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
