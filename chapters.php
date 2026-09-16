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

// Fetch chapters with question counts and user attempt progress
$stmt = $db->prepare("
    SELECT c.*, 
           COUNT(DISTINCT q.id) AS question_count,
           COUNT(DISTINCT qs.id) AS user_attempts,
           COALESCE(MAX(qs.score), 0) AS user_best_score,
           COALESCE(MAX(qs.total_questions), 0) AS user_last_total
    FROM chapters c
    LEFT JOIN questions q ON q.chapter_id = c.id
    LEFT JOIN quiz_sessions qs ON qs.chapter_id = c.id AND qs.user_id = :user_id AND qs.completed_at IS NOT NULL
    WHERE c.subject_id = :subject_id
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.id ASC
");
$stmt->execute(['subject_id' => $subject_id, 'user_id' => $current_user['id']]);
$chapters = $stmt->fetchAll();

// Check if user has any attempts across this subject
$has_subject_attempts = false;
foreach ($chapters as $ch) {
    if ($ch['user_attempts'] > 0) {
        $has_subject_attempts = true;
        break;
    }
}

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
    <div style="margin-top: var(--space-md); display: flex; gap: var(--space-sm); align-items: center; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>/quiz.php?subject_id=<?= $subject['id'] ?>" class="btn btn-primary" id="quiz-all-chapters-btn">
            ⚡ Start 20-Question Subject Quiz (All Chapters)
        </a>
        <?php if ($has_subject_attempts): ?>
            <button type="button" 
                    class="btn btn-secondary" 
                    id="btn-reset-subject" 
                    style="border-color: rgba(239, 68, 68, 0.4); color: #ef4444;"
                    onclick="resetSubjectHistory(<?= $subject['id'] ?>, '<?= e(addslashes($subject['name'])) ?>')">
                ↺ Reset Subject Progress
            </button>
        <?php endif; ?>
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
                <?php if ($chapter['user_attempts'] > 0): ?>
                    <span class="card-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.78rem; font-weight: 600;">
                        ✓ Attempted <?= $chapter['user_attempts'] ?>x (Best: <?= $chapter['user_best_score'] ?>/<?= $chapter['user_last_total'] ?>)
                    </span>
                    <button type="button"
                            class="btn btn-sm btn-reset-chapter"
                            title="Reset progress to start fresh from Question 1 (Tier 1)"
                            style="padding: 4px 10px; font-size: 0.78rem; border-radius: var(--radius-sm); border: 1px solid rgba(239, 68, 68, 0.35); background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer;"
                            onclick="event.preventDefault(); event.stopPropagation(); resetChapterHistory(<?= $chapter['id'] ?>, '<?= e(addslashes($chapter['name'])) ?>')">
                        ↺ Reset
                    </button>
                <?php endif; ?>
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

<!-- Hidden form for CSRF resets -->
<form id="reset-history-form" method="POST" action="<?= BASE_URL ?>/api/reset_history.php" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="scope" id="reset-scope" value="chapter">
    <input type="hidden" name="chapter_id" id="reset-chapter-id" value="">
    <input type="hidden" name="subject_id" id="reset-subject-id" value="">
    <input type="hidden" name="redirect_to" value="<?= e($_SERVER['REQUEST_URI']) ?>">
</form>

<script>
function resetChapterHistory(chapterId, chapterName) {
    if (confirm(`Reset history for "${chapterName}"?\n\nThis will clear your past scores and accuracy for this chapter, restarting your question progression fresh from Tier 1 (Question 1).`)) {
        document.getElementById('reset-scope').value = 'chapter';
        document.getElementById('reset-chapter-id').value = chapterId;
        document.getElementById('reset-subject-id').value = '';
        document.getElementById('reset-history-form').submit();
    }
}

function resetSubjectHistory(subjectId, subjectName) {
    if (confirm(`Reset history for all chapters in "${subjectName}"?\n\nThis will clear all past attempts and accuracy across all chapters in this subject.`)) {
        document.getElementById('reset-scope').value = 'subject';
        document.getElementById('reset-subject-id').value = subjectId;
        document.getElementById('reset-chapter-id').value = '';
        document.getElementById('reset-history-form').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
