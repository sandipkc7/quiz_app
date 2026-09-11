<?php
/**
 * Quiz History Page — Past quiz attempts, scores, and review
 */
require_once __DIR__ . '/includes/auth_guard.php';

$page_title = 'My Quiz History';
$user_id = current_user_id();
$db = getDB();

// Filter by subject if selected
$filter_subject = intval($_GET['subject_id'] ?? 0);

// Get all subjects for filter dropdown
$subjects = $db->query("SELECT id, name, icon FROM subjects ORDER BY name ASC")->fetchAll();

// Query history
$query = "
    SELECT qs.id, qs.score, qs.total_questions, qs.started_at, qs.completed_at,
           c.name AS chapter_name, s.name AS subject_name, s.icon AS subject_icon, s.id AS subject_id
    FROM quiz_sessions qs
    JOIN chapters c ON c.id = qs.chapter_id
    JOIN subjects s ON s.id = c.subject_id
    WHERE qs.user_id = :user_id AND qs.completed_at IS NOT NULL
";
$params = ['user_id' => $user_id];

if ($filter_subject > 0) {
    $query .= " AND s.id = :subject_id";
    $params['subject_id'] = $filter_subject;
}

$query .= " ORDER BY qs.completed_at DESC LIMIT 50";

$stmt = $db->prepare($query);
$stmt->execute($params);
$history = $stmt->fetchAll();

// User overall stats
$stats_stmt = $db->prepare("
    SELECT COUNT(*) AS total_quizzes,
           COALESCE(SUM(score), 0) AS total_correct,
           COALESCE(SUM(total_questions), 0) AS total_attempted,
           COALESCE(MAX(score), 0) AS best_score
    FROM quiz_sessions
    WHERE user_id = :user_id AND completed_at IS NOT NULL
");
$stats_stmt->execute(['user_id' => $user_id]);
$stats = $stats_stmt->fetch();

$overall_pct = $stats['total_attempted'] > 0 ? round(($stats['total_correct'] / $stats['total_attempted']) * 100) : 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">📊 Quiz History & Performance</h1>
    <p class="page-subtitle">Track your progress and review past quiz attempts</p>
</div>

<!-- Stats Overview Cards -->
<div class="dashboard-stats" style="margin-bottom: var(--space-xl);">
    <div class="stat-card">
        <div class="stat-value"><?= $stats['total_quizzes'] ?></div>
        <div class="stat-label">Quizzes Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $overall_pct ?>%</div>
        <div class="stat-label">Average Accuracy</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['total_correct'] ?> / <?= $stats['total_attempted'] ?></div>
        <div class="stat-label">Total Correct Answers</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['best_score'] ?></div>
        <div class="stat-label">Best Score</div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom: var(--space-lg); padding: var(--space-md);">
    <form method="GET" action="" style="display: flex; gap: var(--space-md); align-items: center; flex-wrap: wrap;">
        <label for="subject_filter" style="font-weight: 600; color: var(--text-secondary);">Filter by Subject:</label>
        <select name="subject_id" id="subject_filter" class="form-control" style="max-width: 250px; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);" onchange="this.form.submit()">
            <option value="0">All Subjects</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filter_subject === $s['id'] ? 'selected' : '' ?>>
                    <?= e($s['icon']) ?> <?= e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($filter_subject > 0): ?>
            <a href="<?= BASE_URL ?>/history.php" class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.85rem;">Clear Filter</a>
        <?php endif; ?>
    </form>
</div>

<!-- History List -->
<?php if (empty($history)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📝</div>
        <p class="empty-state-text">No completed quiz sessions found <?= $filter_subject ? 'for this subject' : 'yet' ?>.</p>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">Take a Quiz Now</a>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: var(--space-md);">
        <?php foreach ($history as $item): 
            $pct = $item['total_questions'] > 0 ? round(($item['score'] / $item['total_questions']) * 100) : 0;
            $badge_color = $pct >= 80 ? 'var(--color-success)' : ($pct >= 50 ? 'var(--accent-primary)' : 'var(--color-danger)');
        ?>
        <div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-md) var(--space-lg); flex-wrap: wrap; gap: var(--space-md);">
            <div style="display: flex; align-items: center; gap: var(--space-md);">
                <div style="font-size: 2rem;"><?= e($item['subject_icon']) ?></div>
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">
                        <?= e($item['subject_name']) ?> &bull; <?= e($item['chapter_name']) ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        Completed: <?= date('M d, Y h:i A', strtotime($item['completed_at'])) ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: var(--space-xl);">
                <div style="text-align: right;">
                    <div style="font-size: 1.3rem; font-weight: 800; color: <?= $badge_color ?>;">
                        <?= $item['score'] ?> / <?= $item['total_questions'] ?>
                    </div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);"><?= $pct ?>% Accuracy</div>
                </div>

                <a href="<?= BASE_URL ?>/result.php?session_id=<?= $item['id'] ?>" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.85rem;">
                    Review Answers →
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
