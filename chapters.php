<?php
/**
 * Chapter Selection Page
 */
require_once __DIR__ . '/includes/auth_guard.php';

$subject_id = intval($_GET['subject_id'] ?? 0);
if ($subject_id <= 0) {
    flash('error', 'Invalid subject selected.');
    redirect('/dashboard.php');
}

$db = getDB();

// Fetch subject
$stmt = $db->prepare("SELECT * FROM subjects WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $subject_id]);
$subject = $stmt->fetch();

if (!$subject) {
    flash('error', 'Subject not found.');
    redirect('/dashboard.php');
}

$page_title = $subject['name'] . ' — Chapters';

// Fetch chapters with question counts
$stmt = $db->prepare("
    SELECT c.*, COUNT(q.id) AS question_count
    FROM chapters c
    LEFT JOIN questions q ON q.chapter_id = c.id
    WHERE c.subject_id = :subject_id
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.id ASC
");
$stmt->execute(['subject_id' => $subject_id]);
$chapters = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<a href="<?= BASE_URL ?>/dashboard.php" class="back-link">
    ← Back to Subjects
</a>

<div class="page-header">
    <h1 class="page-title"><?= e($subject['icon']) ?> <?= e($subject['name']) ?></h1>
    <?php if ($subject['name_local']): ?>
        <p class="page-subtitle" style="color: var(--accent-secondary); margin-bottom: var(--space-sm);">
            <?= e($subject['name_local']) ?>
        </p>
    <?php endif; ?>
    <p class="page-subtitle"><?= e($subject['description']) ?></p>
    <div style="margin-top: var(--space-md);">
        <a href="<?= BASE_URL ?>/quiz.php?subject_id=<?= $subject['id'] ?>" class="btn btn-primary" id="quiz-all-chapters-btn">
            ⚡ Start 20-Question Subject Quiz (All Chapters)
        </a>
    </div>
</div>

<?php if (empty($chapters)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <p class="empty-state-text">No chapters available for this subject yet.</p>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
<?php else: ?>
    <div class="chapter-list" id="chapters-list">
        <?php foreach ($chapters as $index => $chapter): ?>
        <a href="<?= $chapter['question_count'] >= 1 ? BASE_URL . '/quiz.php?chapter_id=' . $chapter['id'] : '#' ?>"
           class="chapter-card <?= $chapter['question_count'] < 1 ? 'disabled' : '' ?>"
           id="chapter-<?= $chapter['id'] ?>"
           <?= $chapter['question_count'] < 1 ? 'style="opacity:0.5; cursor:not-allowed;" onclick="event.preventDefault();"' : '' ?>>
            <div class="chapter-info">
                <div class="chapter-number"><?= $index + 1 ?></div>
                <div>
                    <div class="chapter-name"><?= e($chapter['name']) ?></div>
                    <?php if ($chapter['name_local']): ?>
                        <div class="chapter-name-local"><?= e($chapter['name_local']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="chapter-meta">
                <span class="chapter-question-count"><?= $chapter['question_count'] ?> questions</span>
                <?php if ($chapter['question_count'] >= 1): ?>
                    <span class="chapter-arrow">→</span>
                <?php else: ?>
                    <span class="card-badge" style="color: var(--text-muted);">Coming Soon</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
