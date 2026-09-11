<?php
/**
 * Admin: Manage Subjects & Chapters
 */
$page_title = 'Manage Subjects';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$current_user = current_user();

$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // --- Add Subject ---
    if ($action === 'add_subject') {
        $name       = trim($_POST['name'] ?? '');
        $name_local = trim($_POST['name_local'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon       = trim($_POST['icon'] ?? '📚');

        if (!empty($name)) {
            $stmt = $db->prepare("INSERT INTO subjects (name, name_local, description, icon) VALUES (:name, :name_local, :desc, :icon)");
            $stmt->execute(['name' => $name, 'name_local' => $name_local ?: null, 'desc' => $description, 'icon' => $icon]);
            flash('success', 'Subject added successfully!');
        }
        redirect('/admin/manage_subjects.php');
    }

    // --- Delete Subject ---
    if ($action === 'delete_subject') {
        $id = intval($_POST['subject_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM subjects WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash('success', 'Subject deleted.');
        }
        redirect('/admin/manage_subjects.php');
    }

    // --- Add Chapter ---
    if ($action === 'add_chapter') {
        $subject_id = intval($_POST['subject_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $name_local = trim($_POST['name_local'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);

        if ($subject_id > 0 && !empty($name)) {
            $stmt = $db->prepare("INSERT INTO chapters (subject_id, name, name_local, sort_order) VALUES (:sid, :name, :name_local, :sort)");
            $stmt->execute(['sid' => $subject_id, 'name' => $name, 'name_local' => $name_local ?: null, 'sort' => $sort_order]);
            flash('success', 'Chapter added successfully!');
        }
        redirect('/admin/manage_subjects.php');
    }

    // --- Delete Chapter ---
    if ($action === 'delete_chapter') {
        $id = intval($_POST['chapter_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM chapters WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash('success', 'Chapter deleted.');
        }
        redirect('/admin/manage_subjects.php');
    }
}

// Fetch subjects with chapters
$subjects = $db->query("SELECT * FROM subjects ORDER BY id ASC")->fetchAll();
$chapters = $db->query("
    SELECT c.*, s.name AS subject_name, COUNT(q.id) AS question_count
    FROM chapters c
    JOIN subjects s ON s.id = c.subject_id
    LEFT JOIN questions q ON q.chapter_id = c.id
    GROUP BY c.id
    ORDER BY c.subject_id, c.sort_order ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<a href="<?= BASE_URL ?>/admin/index.php" class="back-link">← Back to Admin</a>

<div class="page-header">
    <h1 class="page-title">📚 Manage Subjects & Chapters</h1>
</div>

<!-- Add Subject Form -->
<div class="question-card mb-xl">
    <h2 style="font-size: var(--font-size-lg); font-weight: 700; margin-bottom: var(--space-lg);">Add New Subject</h2>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_subject">
        <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: var(--space-md); margin-bottom: var(--space-md);">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Icon</label>
                <input type="text" name="icon" class="form-input" value="📚" maxlength="10">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Subject Name</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Science" required>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Local Name (optional)</label>
                <input type="text" name="name_local" class="form-input" placeholder="e.g. विज्ञान">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-input" placeholder="Brief description of the subject">
        </div>
        <button type="submit" class="btn btn-primary">Add Subject</button>
    </form>
</div>

<!-- Subjects List -->
<div class="table-container mb-xl">
    <table class="data-table">
        <thead>
            <tr>
                <th>Icon</th>
                <th>Name</th>
                <th>Local Name</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subjects as $subject): ?>
            <tr>
                <td><?= $subject['icon'] ?></td>
                <td style="color: var(--text-primary); font-weight: 600;"><?= e($subject['name']) ?></td>
                <td style="color: var(--accent-secondary);"><?= e($subject['name_local'] ?? '—') ?></td>
                <td><?= e($subject['description'] ?? '—') ?></td>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this subject and all its chapters/questions?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_subject">
                        <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Chapter Form -->
<div class="question-card mb-xl">
    <h2 style="font-size: var(--font-size-lg); font-weight: 700; margin-bottom: var(--space-lg);">Add New Chapter</h2>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_chapter">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 80px; gap: var(--space-md); margin-bottom: var(--space-md);">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select" required>
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Chapter Name</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Physics Basics" required>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Local Name (optional)</label>
                <input type="text" name="name_local" class="form-input" placeholder="e.g. भौतिकशास्त्र">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Order</label>
                <input type="number" name="sort_order" class="form-input" value="0" min="0">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Chapter</button>
    </form>
</div>

<!-- Chapters List -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Chapter</th>
                <th>Local Name</th>
                <th>Questions</th>
                <th>Order</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($chapters as $ch): ?>
            <tr>
                <td><?= e($ch['subject_name']) ?></td>
                <td style="color: var(--text-primary); font-weight: 600;"><?= e($ch['name']) ?></td>
                <td style="color: var(--accent-secondary);"><?= e($ch['name_local'] ?? '—') ?></td>
                <td><span class="card-badge"><?= $ch['question_count'] ?></span></td>
                <td><?= $ch['sort_order'] ?></td>
                <td>
                    <div class="table-actions">
                        <a href="<?= BASE_URL ?>/admin/manage_questions.php?chapter_id=<?= $ch['id'] ?>" class="btn btn-secondary btn-sm">Questions</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this chapter and all its questions?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_chapter">
                            <input type="hidden" name="chapter_id" value="<?= $ch['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
