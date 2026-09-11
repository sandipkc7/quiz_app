<?php
/**
 * Dashboard — Subject Selection
 */
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/auth_guard.php';

$db = getDB();

// Fetch all subjects with chapter and question counts
$subjects = $db->query("
    SELECT s.*,
           COUNT(DISTINCT c.id) AS chapter_count,
           COUNT(DISTINCT q.id) AS question_count
    FROM subjects s
    LEFT JOIN chapters c ON c.subject_id = s.id
    LEFT JOIN questions q ON q.chapter_id = c.id
    GROUP BY s.id
    ORDER BY s.id ASC
")->fetchAll();

// Fetch user's recent quiz history
$stmt = $db->prepare("
    SELECT qs.score, qs.total_questions, qs.completed_at,
           c.name AS chapter_name, s.name AS subject_name
    FROM quiz_sessions qs
    JOIN chapters c ON c.id = qs.chapter_id
    JOIN subjects s ON s.id = c.subject_id
    WHERE qs.user_id = :user_id AND qs.completed_at IS NOT NULL
    ORDER BY qs.completed_at DESC
    LIMIT 5
");
$stmt->execute(['user_id' => $current_user['id']]);
$recent_quizzes = $stmt->fetchAll();

// Stats
$stmt = $db->prepare("
    SELECT
        COUNT(*) AS total_quizzes,
        COALESCE(SUM(score), 0) AS total_correct,
        COALESCE(SUM(total_questions), 0) AS total_questions
    FROM quiz_sessions
    WHERE user_id = :user_id AND completed_at IS NOT NULL
");
$stmt->execute(['user_id' => $current_user['id']]);
$stats = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Choose a Subject</h1>
    <p class="page-subtitle">Select a subject to explore chapters and start a quiz</p>
</div>

<!-- User Stats -->
<?php if ($stats['total_quizzes'] > 0): ?>
<div class="stats-row">
    <div class="stat-card">
        <span class="stat-value"><?= $stats['total_quizzes'] ?></span>
        <span class="stat-label">Quizzes Taken</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['total_correct'] ?></span>
        <span class="stat-label">Correct Answers</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['total_questions'] > 0 ? round(($stats['total_correct'] / $stats['total_questions']) * 100) : 0 ?>%</span>
        <span class="stat-label">Accuracy</span>
    </div>
</div>
<?php endif; ?>

<!-- Subject Cards -->
<div class="card-grid" id="subjects-grid">
    <?php foreach ($subjects as $subject): ?>
    <a href="<?= BASE_URL ?>/chapters.php?subject_id=<?= $subject['id'] ?>" class="card" id="subject-<?= $subject['id'] ?>">
        <span class="card-icon"><?= $subject['icon'] ?></span>
        <h2 class="card-title"><?= e($subject['name']) ?></h2>
        <?php if ($subject['name_local']): ?>
            <p class="card-title-local"><?= e($subject['name_local']) ?></p>
        <?php endif; ?>
        <p class="card-description"><?= e($subject['description']) ?></p>
        <div class="card-meta">
            <span class="card-badge"><?= $subject['chapter_count'] ?> Chapters</span>
            <span class="card-badge"><?= $subject['question_count'] ?> Questions</span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Recent Activity -->
<?php if (!empty($recent_quizzes)): ?>
<div class="mt-xl">
    <h2 class="page-title" style="font-size: var(--font-size-xl);">Recent Activity</h2>
    <div class="chapter-list mt-md">
        <?php foreach ($recent_quizzes as $quiz): ?>
        <div class="chapter-card" style="cursor: default;">
            <div class="chapter-info">
                <div class="chapter-number"><?= $quiz['score'] ?></div>
                <div>
                    <div class="chapter-name"><?= e($quiz['chapter_name']) ?></div>
                    <div class="chapter-name-local"><?= e($quiz['subject_name']) ?> — <?= date('M j, Y', strtotime($quiz['completed_at'])) ?></div>
                </div>
            </div>
            <div class="chapter-meta">
                <span class="card-badge"><?= $quiz['score'] ?>/<?= $quiz['total_questions'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
