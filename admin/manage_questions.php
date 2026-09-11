<?php
/**
 * Admin: Manage Questions
 */
$page_title = 'Manage Questions';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$current_user = current_user();

$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // --- Add Question ---
    if ($action === 'add_question') {
        $chapter_id     = intval($_POST['chapter_id'] ?? 0);
        $question_text  = trim($_POST['question_text'] ?? '');
        $option_a       = trim($_POST['option_a'] ?? '');
        $option_b       = trim($_POST['option_b'] ?? '');
        $option_c       = trim($_POST['option_c'] ?? '');
        $option_d       = trim($_POST['option_d'] ?? '');
        $correct_option = strtoupper(trim($_POST['correct_option'] ?? ''));
        $explanation    = trim($_POST['explanation'] ?? '');

        if ($chapter_id > 0 && !empty($question_text) && !empty($option_a) && !empty($option_b) &&
            !empty($option_c) && !empty($option_d) && in_array($correct_option, ['A','B','C','D'])) {

            $stmt = $db->prepare("
                INSERT INTO questions (chapter_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation)
                VALUES (:cid, :qt, :oa, :ob, :oc, :od, :co, :exp)
            ");
            $stmt->execute([
                'cid' => $chapter_id, 'qt' => $question_text,
                'oa' => $option_a, 'ob' => $option_b, 'oc' => $option_c, 'od' => $option_d,
                'co' => $correct_option, 'exp' => $explanation ?: null
            ]);
            flash('success', 'Question added successfully!');
        } else {
            flash('error', 'Please fill in all required fields.');
        }
        $redirect = $chapter_id > 0 ? '/admin/manage_questions.php?chapter_id=' . $chapter_id : '/admin/manage_questions.php';
        redirect($redirect);
    }

    // --- Delete Question ---
    if ($action === 'delete_question') {
        $id = intval($_POST['question_id'] ?? 0);
        $chapter_id = intval($_POST['chapter_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM questions WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash('success', 'Question deleted.');
        }
        $redirect = $chapter_id > 0 ? '/admin/manage_questions.php?chapter_id=' . $chapter_id : '/admin/manage_questions.php';
        redirect($redirect);
    }
}

// Current filter
$filter_chapter = intval($_GET['chapter_id'] ?? 0);

// Fetch chapters for filter
$chapters = $db->query("
    SELECT c.id, c.name, s.name AS subject_name
    FROM chapters c
    JOIN subjects s ON s.id = c.subject_id
    ORDER BY s.name, c.sort_order
")->fetchAll();

// Fetch questions
if ($filter_chapter > 0) {
    $stmt = $db->prepare("
        SELECT q.*, c.name AS chapter_name, s.name AS subject_name, cs.title AS case_study_title
        FROM questions q
        JOIN chapters c ON c.id = q.chapter_id
        JOIN subjects s ON s.id = c.subject_id
        LEFT JOIN case_studies cs ON cs.id = q.case_study_id
        WHERE q.chapter_id = :cid
        ORDER BY q.id DESC
    ");
    $stmt->execute(['cid' => $filter_chapter]);
} else {
    $stmt = $db->query("
        SELECT q.*, c.name AS chapter_name, s.name AS subject_name, cs.title AS case_study_title
        FROM questions q
        JOIN chapters c ON c.id = q.chapter_id
        JOIN subjects s ON s.id = c.subject_id
        LEFT JOIN case_studies cs ON cs.id = q.case_study_id
        ORDER BY q.id DESC
        LIMIT 50
    ");
}
$questions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<a href="<?= BASE_URL ?>/admin/index.php" class="back-link">← Back to Admin</a>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-md);">
    <h1 class="page-title">❓ Manage Questions</h1>
    <a href="<?= BASE_URL ?>/admin/import_questions.php" class="btn btn-primary">
        🤖 Bulk AI & CSV Import
    </a>
</div>

<!-- Filter by Chapter -->
<div class="question-card mb-xl" style="padding: var(--space-lg);">
    <form method="GET" action="" style="display: flex; align-items: end; gap: var(--space-md);">
        <div class="form-group" style="margin-bottom:0; flex: 1;">
            <label class="form-label">Filter by Chapter</label>
            <select name="chapter_id" class="form-select" onchange="this.form.submit()">
                <option value="0">All Chapters (recent 50)</option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= $ch['id'] ?>" <?= $filter_chapter === $ch['id'] ? 'selected' : '' ?>>
                        <?= e($ch['subject_name']) ?> → <?= e($ch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<!-- Add Question Form -->
<div class="question-card mb-xl">
    <h2 style="font-size: var(--font-size-lg); font-weight: 700; margin-bottom: var(--space-lg);">Add New Question</h2>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_question">

        <div class="form-group">
            <label class="form-label">Chapter</label>
            <select name="chapter_id" class="form-select" required>
                <option value="">Select Chapter</option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= $ch['id'] ?>" <?= $filter_chapter === $ch['id'] ? 'selected' : '' ?>>
                        <?= e($ch['subject_name']) ?> → <?= e($ch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Question Text</label>
            <textarea name="question_text" class="form-textarea" placeholder="Enter the question (supports Unicode/multi-language)" required></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
            <div class="form-group">
                <label class="form-label">Option A</label>
                <input type="text" name="option_a" class="form-input" placeholder="Option A" required>
            </div>
            <div class="form-group">
                <label class="form-label">Option B</label>
                <input type="text" name="option_b" class="form-input" placeholder="Option B" required>
            </div>
            <div class="form-group">
                <label class="form-label">Option C</label>
                <input type="text" name="option_c" class="form-input" placeholder="Option C" required>
            </div>
            <div class="form-group">
                <label class="form-label">Option D</label>
                <input type="text" name="option_d" class="form-input" placeholder="Option D" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 200px 1fr; gap: var(--space-md);">
            <div class="form-group">
                <label class="form-label">Correct Answer</label>
                <select name="correct_option" class="form-select" required>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Explanation (optional)</label>
                <input type="text" name="explanation" class="form-input" placeholder="Why this is the correct answer...">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Add Question</button>
    </form>
</div>

<!-- Questions List -->
<h2 style="font-size: var(--font-size-lg); font-weight: 700; margin-bottom: var(--space-lg);">
    Questions (<?= count($questions) ?>)
</h2>

<?php if (empty($questions)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <p class="empty-state-text">No questions found. Add one above!</p>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Question</th>
                    <th>A</th>
                    <th>B</th>
                    <th>C</th>
                    <th>D</th>
                    <th>Ans</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $q): ?>
                <tr>
                    <td><?= $q['id'] ?></td>
                    <td style="color: var(--text-primary); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                        title="<?= e($q['question_text']) ?>">
                        <?php if (!empty($q['case_study_title'])): ?>
                            <span class="card-badge" style="background: rgba(0, 206, 201, 0.15); color: var(--accent-secondary); font-size: 0.68rem; padding: 2px 6px; margin-right: 4px;" title="Case Study: <?= e($q['case_study_title']) ?>">📖 Case</span>
                        <?php endif; ?>
                        <?= e(mb_substr($q['question_text'], 0, 50, 'UTF-8')) ?><?= mb_strlen($q['question_text'], 'UTF-8') > 50 ? '...' : '' ?>
                    </td>
                    <td title="<?= e($q['option_a']) ?>"><?= e(mb_substr($q['option_a'], 0, 15, 'UTF-8')) ?></td>
                    <td title="<?= e($q['option_b']) ?>"><?= e(mb_substr($q['option_b'], 0, 15, 'UTF-8')) ?></td>
                    <td title="<?= e($q['option_c']) ?>"><?= e(mb_substr($q['option_c'], 0, 15, 'UTF-8')) ?></td>
                    <td title="<?= e($q['option_d']) ?>"><?= e(mb_substr($q['option_d'], 0, 15, 'UTF-8')) ?></td>
                    <td><span class="card-badge" style="color: var(--success);"><?= e($q['correct_option']) ?></span></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this question?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                            <input type="hidden" name="chapter_id" value="<?= $filter_chapter ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
