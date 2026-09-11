<?php
/**
 * Result Page — Display quiz score
 */
require_once __DIR__ . '/includes/auth_guard.php';

$session_id = intval($_GET['session_id'] ?? 0);
if ($session_id <= 0) {
    redirect('/dashboard.php');
}

$db = getDB();

// Fetch session with chapter info
$stmt = $db->prepare("
    SELECT qs.*, c.name AS chapter_name, c.name_local AS chapter_name_local,
           s.name AS subject_name, s.id AS subject_id, s.icon AS subject_icon
    FROM quiz_sessions qs
    JOIN chapters c ON c.id = qs.chapter_id
    JOIN subjects s ON s.id = c.subject_id
    WHERE qs.id = :id AND qs.user_id = :user_id
    LIMIT 1
");
$stmt->execute(['id' => $session_id, 'user_id' => $current_user['id']]);
$session = $stmt->fetch();

if (!$session) {
    flash('error', 'Quiz session not found.');
    redirect('/dashboard.php');
}

$page_title = 'Quiz Results';

$score = $session['score'];
$total = $session['total_questions'];
$percentage = $total > 0 ? round(($score / $total) * 100) : 0;
$wrong = $total - $score;

// Determine performance level
if ($percentage >= 80) {
    $icon = '🏆';
    $title = 'Excellent!';
    $bar_class = 'excellent';
} elseif ($percentage >= 60) {
    $icon = '🎯';
    $title = 'Good Job!';
    $bar_class = 'good';
} elseif ($percentage >= 40) {
    $icon = '💪';
    $title = 'Keep Trying!';
    $bar_class = 'average';
} else {
    $icon = '📖';
    $title = 'Study More!';
    $bar_class = 'poor';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="result-container">
    <div class="result-card">
        <div class="result-icon"><?= $icon ?></div>
        <h1 class="result-title"><?= $title ?></h1>

        <div class="result-score" id="result-score" data-score="<?= $score ?>" data-total="<?= $total ?>">
            <?= $score ?> / <?= $total ?>
        </div>

        <div class="result-percentage"><?= $percentage ?>% Accuracy</div>

        <div class="result-bar">
            <div class="result-bar-fill <?= $bar_class ?>" id="result-bar-fill" data-width="<?= $percentage ?>"></div>
        </div>

        <div class="result-stats">
            <div class="result-stat">
                <span class="result-stat-value correct"><?= $score ?></span>
                <span class="result-stat-label">Correct</span>
            </div>
            <div class="result-stat">
                <span class="result-stat-value wrong"><?= $wrong ?></span>
                <span class="result-stat-label">Wrong</span>
            </div>
            <div class="result-stat">
                <span class="result-stat-value total"><?= $total ?></span>
                <span class="result-stat-label">Total</span>
            </div>
        </div>

        <p style="color: var(--text-muted); font-size: var(--font-size-sm); margin-bottom: var(--space-xl);">
            <?= e($session['subject_icon']) ?> <?= e($session['subject_name']) ?> — <?= e($session['chapter_name']) ?>
            <?php if ($session['chapter_name_local']): ?>
                <br><span style="color: var(--accent-secondary);"><?= e($session['chapter_name_local']) ?></span>
            <?php endif; ?>
        </p>

        <div class="result-actions">
            <a href="<?= BASE_URL ?>/quiz.php?chapter_id=<?= $session['chapter_id'] ?>" class="btn btn-primary btn-lg">
                🔄 Retake Quiz
            </a>
            <a href="<?= BASE_URL ?>/chapters.php?subject_id=<?= $session['subject_id'] ?>" class="btn btn-secondary btn-lg">
                📚 More Chapters
            </a>
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary btn-lg">
                🏠 Dashboard
            </a>
        </div>
    </div>
</div>

<script>
    // Animate the result bar on load
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const bar = document.getElementById('result-bar-fill');
            if (bar) {
                bar.style.width = bar.dataset.width + '%';
            }
        }, 300);
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
