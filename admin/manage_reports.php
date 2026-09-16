<?php
/**
 * Admin: Manage Question Reports
 * View, fix, resolve, and dismiss student question reports
 */
$page_title = 'Question Reports — Admin';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = getDB();
$current_user = current_user();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!csrf_verify($csrf)) {
        flash('error', 'Invalid form submission.');
        redirect('/admin/manage_reports.php');
    }

    if ($action === 'fix_and_resolve') {
        $report_id      = intval($_POST['report_id'] ?? 0);
        $question_id    = intval($_POST['question_id'] ?? 0);
        $question_text  = trim($_POST['question_text'] ?? '');
        $option_a       = trim($_POST['option_a'] ?? '');
        $option_b       = trim($_POST['option_b'] ?? '');
        $option_c       = trim($_POST['option_c'] ?? '');
        $option_d       = trim($_POST['option_d'] ?? '');
        $correct_option = strtoupper(trim($_POST['correct_option'] ?? ''));
        $explanation    = trim($_POST['explanation'] ?? '');
        $admin_notes    = trim($_POST['admin_notes'] ?? '');

        if ($report_id > 0 && $question_id > 0 && !empty($question_text) && in_array($correct_option, ['A', 'B', 'C', 'D'])) {
            // Update question
            $stmt = $db->prepare("
                UPDATE questions
                SET question_text = :qtext,
                    option_a = :oa,
                    option_b = :ob,
                    option_c = :oc,
                    option_d = :od,
                    correct_option = :co,
                    explanation = :exp
                WHERE id = :qid
            ");
            $stmt->execute([
                'qtext' => $question_text,
                'oa'    => $option_a,
                'ob'    => $option_b,
                'oc'    => $option_c,
                'od'    => $option_d,
                'co'    => $correct_option,
                'exp'   => $explanation,
                'qid'   => $question_id
            ]);

            // Mark report as resolved
            $stmt = $db->prepare("
                UPDATE question_reports
                SET status = 'resolved',
                    resolved_by = :uid,
                    admin_notes = :notes,
                    resolved_at = NOW()
                WHERE id = :rid
            ");
            $stmt->execute([
                'uid'   => $current_user['id'],
                'notes' => !empty($admin_notes) ? $admin_notes : 'Fixed question and verified correct answer.',
                'rid'   => $report_id
            ]);

            flash('success', "Question #$question_id updated and report #$report_id marked resolved.");
        } else {
            flash('error', 'Please fill in all required question fields.');
        }
        redirect('/admin/manage_reports.php?status=' . urlencode($_GET['status'] ?? 'pending'));

    } elseif ($action === 'update_status') {
        $report_id   = intval($_POST['report_id'] ?? 0);
        $new_status  = $_POST['status'] ?? '';
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        if ($report_id > 0 && in_array($new_status, ['resolved', 'dismissed', 'pending'])) {
            $stmt = $db->prepare("
                UPDATE question_reports
                SET status = :status,
                    resolved_by = :uid,
                    admin_notes = :notes,
                    resolved_at = " . ($new_status === 'pending' ? 'NULL' : 'NOW()') . "
                WHERE id = :rid
            ");
            $stmt->execute([
                'status' => $new_status,
                'uid'    => $new_status === 'pending' ? null : $current_user['id'],
                'notes'  => !empty($admin_notes) ? $admin_notes : null,
                'rid'    => $report_id
            ]);

            flash('success', "Report #$report_id status updated to " . ucfirst($new_status) . ".");
        }
        redirect('/admin/manage_reports.php?status=' . urlencode($_GET['status'] ?? 'pending'));

    } elseif ($action === 'delete_question') {
        $question_id = intval($_POST['question_id'] ?? 0);
        if ($question_id > 0) {
            $stmt = $db->prepare("DELETE FROM questions WHERE id = :id");
            $stmt->execute(['id' => $question_id]);
            flash('success', "Question #$question_id and its associated reports have been deleted.");
        }
        redirect('/admin/manage_reports.php?status=' . urlencode($_GET['status'] ?? 'pending'));
    }
}

// Current filter
$filter_status = $_GET['status'] ?? 'pending';
if (!in_array($filter_status, ['pending', 'resolved', 'dismissed', 'all'])) {
    $filter_status = 'pending';
}

// Counts
$counts = [
    'pending'   => $db->query("SELECT COUNT(*) FROM question_reports WHERE status = 'pending'")->fetchColumn(),
    'resolved'  => $db->query("SELECT COUNT(*) FROM question_reports WHERE status = 'resolved'")->fetchColumn(),
    'dismissed' => $db->query("SELECT COUNT(*) FROM question_reports WHERE status = 'dismissed'")->fetchColumn(),
    'all'       => $db->query("SELECT COUNT(*) FROM question_reports")->fetchColumn(),
];

// Query reports
$where = ($filter_status === 'all') ? '' : 'WHERE qr.status = :status';
$stmt = $db->prepare("
    SELECT qr.*,
           u.username AS reporter_username,
           u.email AS reporter_email,
           ru.username AS resolver_username,
           q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.explanation,
           q.chapter_id,
           c.name AS chapter_name,
           s.name AS subject_name,
           s.icon AS subject_icon
    FROM question_reports qr
    JOIN users u ON u.id = qr.user_id
    LEFT JOIN users ru ON ru.id = qr.resolved_by
    LEFT JOIN questions q ON q.id = qr.question_id
    LEFT JOIN chapters c ON c.id = q.chapter_id
    LEFT JOIN subjects s ON s.id = c.subject_id
    $where
    ORDER BY qr.created_at DESC
");

if ($filter_status !== 'all') {
    $stmt->execute(['status' => $filter_status]);
} else {
    $stmt->execute();
}
$reports = $stmt->fetchAll();

$reason_labels = [
    'wrong_answer' => ['label' => '❌ Wrong Answer', 'class' => 'report-badge-pending'],
    'incomplete'   => ['label' => '⚠️ Incomplete', 'class' => 'report-badge-pending'],
    'irrelevant'   => ['label' => '🚫 Irrelevant', 'class' => 'report-badge-pending'],
    'other'        => ['label' => '💬 Other', 'class' => 'report-badge-dismissed']
];

require_once __DIR__ . '/../includes/header.php';
?>

<a href="<?= BASE_URL ?>/admin/index.php" class="back-link">← Back to Admin Panel</a>

<div class="page-header flex-between align-center flex-wrap gap-md">
    <div>
        <h1 class="page-title">🚩 Question Reports</h1>
        <p class="page-subtitle">Review, fix, and resolve student-reported question issues</p>
    </div>
</div>

<!-- Tabs -->
<div class="tabs" style="display: flex; gap: 8px; margin-bottom: var(--space-xl); border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
    <a href="?status=pending" class="btn btn-sm <?= $filter_status === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">
        Pending (<?= $counts['pending'] ?>)
    </a>
    <a href="?status=resolved" class="btn btn-sm <?= $filter_status === 'resolved' ? 'btn-primary' : 'btn-secondary' ?>">
        Resolved (<?= $counts['resolved'] ?>)
    </a>
    <a href="?status=dismissed" class="btn btn-sm <?= $filter_status === 'dismissed' ? 'btn-primary' : 'btn-secondary' ?>">
        Dismissed (<?= $counts['dismissed'] ?>)
    </a>
    <a href="?status=all" class="btn btn-sm <?= $filter_status === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
        All Reports (<?= $counts['all'] ?>)
    </a>
</div>

<?php if (empty($reports)): ?>
    <div class="card text-center" style="padding: var(--space-3xl) var(--space-xl);">
        <span style="font-size: 3rem; display: block; margin-bottom: var(--space-md);">🎉</span>
        <h2 style="font-size: var(--font-size-xl); margin-bottom: var(--space-sm); color: var(--text-primary);">No <?= htmlspecialchars($filter_status) ?> reports found</h2>
        <p style="color: var(--text-secondary);">All questions in this category are in good standing.</p>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: var(--space-lg);">
        <?php foreach ($reports as $r):
            $r_info = $reason_labels[$r['reason']] ?? ['label' => ucfirst($r['reason']), 'class' => 'report-badge-dismissed'];
        ?>
        <div class="card" id="report-card-<?= $r['id'] ?>" style="padding: var(--space-lg); border-left: 4px solid <?= $r['status'] === 'pending' ? 'var(--warning)' : ($r['status'] === 'resolved' ? 'var(--success)' : 'var(--text-muted)') ?>;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-md); margin-bottom: var(--space-md); flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span class="report-badge report-badge-<?= $r['status'] ?>">
                        <?= strtoupper($r['status']) ?>
                    </span>
                    <span class="card-badge" style="background: var(--bg-card); font-weight: 600;">
                        <?= $r_info['label'] ?>
                    </span>
                    <?php if ($r['subject_name']): ?>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">
                            <?= e($r['subject_icon']) ?> <?= e($r['subject_name']) ?> &rsaquo; <?= e($r['chapter_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted);">
                    Reported by <strong style="color: var(--text-primary);"><?= e($r['reporter_username']) ?></strong> &bull; <?= date('M j, Y H:i', strtotime($r['created_at'])) ?>
                </div>
            </div>

            <!-- Reported User Notes -->
            <?php if (!empty($r['details'])): ?>
                <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: var(--space-md);">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--warning); margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Student Feedback:
                    </div>
                    <div style="font-size: 0.88rem; color: var(--text-primary); line-height: 1.4;">
                        &ldquo;<?= nl2br(e($r['details'])) ?>&rdquo;
                    </div>
                </div>
            <?php endif; ?>

            <!-- Question Context -->
            <?php if ($r['question_text']): ?>
                <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; margin-bottom: var(--space-md);">
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 6px;">
                        QUESTION #<?= $r['question_id'] ?>:
                    </div>
                    <div style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 10px; white-space: pre-line; line-height: 1.5;">
                        <?= e($r['question_text']) ?>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; font-size: 0.85rem;">
                        <div style="padding: 6px 10px; border-radius: var(--radius-sm); background: <?= $r['correct_option'] === 'A' ? 'var(--success-bg)' : 'var(--bg-card)' ?>; border: 1px solid <?= $r['correct_option'] === 'A' ? 'var(--success-border)' : 'var(--border-color)' ?>; color: var(--text-primary); white-space: pre-line;">
                            <strong>A:</strong> <?= e($r['option_a']) ?> <?= $r['correct_option'] === 'A' ? '✓' : '' ?>
                        </div>
                        <div style="padding: 6px 10px; border-radius: var(--radius-sm); background: <?= $r['correct_option'] === 'B' ? 'var(--success-bg)' : 'var(--bg-card)' ?>; border: 1px solid <?= $r['correct_option'] === 'B' ? 'var(--success-border)' : 'var(--border-color)' ?>; color: var(--text-primary); white-space: pre-line;">
                            <strong>B:</strong> <?= e($r['option_b']) ?> <?= $r['correct_option'] === 'B' ? '✓' : '' ?>
                        </div>
                        <div style="padding: 6px 10px; border-radius: var(--radius-sm); background: <?= $r['correct_option'] === 'C' ? 'var(--success-bg)' : 'var(--bg-card)' ?>; border: 1px solid <?= $r['correct_option'] === 'C' ? 'var(--success-border)' : 'var(--border-color)' ?>; color: var(--text-primary); white-space: pre-line;">
                            <strong>C:</strong> <?= e($r['option_c']) ?> <?= $r['correct_option'] === 'C' ? '✓' : '' ?>
                        </div>
                        <div style="padding: 6px 10px; border-radius: var(--radius-sm); background: <?= $r['correct_option'] === 'D' ? 'var(--success-bg)' : 'var(--bg-card)' ?>; border: 1px solid <?= $r['correct_option'] === 'D' ? 'var(--success-border)' : 'var(--border-color)' ?>; color: var(--text-primary); white-space: pre-line;">
                            <strong>D:</strong> <?= e($r['option_d']) ?> <?= $r['correct_option'] === 'D' ? '✓' : '' ?>
                        </div>
                    </div>
                    <?php if (!empty($r['explanation'])): ?>
                        <div style="margin-top: 10px; font-size: 0.8rem; color: var(--info); white-space: pre-line; line-height: 1.45;">
                            <strong>Explanation:</strong> <?= e($r['explanation']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="padding: 10px; color: var(--danger); font-size: 0.85rem; font-style: italic;">
                    (The associated question has been deleted)
                </div>
            <?php endif; ?>

            <!-- Resolution Info -->
            <?php if ($r['status'] !== 'pending'): ?>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: var(--space-md);">
                    Resolved by <strong><?= e($r['resolver_username'] ?? 'Admin') ?></strong> on <?= date('M j, Y H:i', strtotime($r['resolved_at'])) ?>
                    <?php if (!empty($r['admin_notes'])): ?>
                        <br><em>Notes: <?= e($r['admin_notes']) ?></em>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; border-top: 1px solid var(--border-color); padding-top: 12px;">
                <?php if ($r['question_text']): ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openFixModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)">
                        🛠️ Fix Question
                    </button>
                <?php endif; ?>

                <?php if ($r['status'] !== 'resolved'): ?>
                    <form method="POST" action="" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="status" value="resolved">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--success);" onclick="return confirm('Mark this report as resolved?')">
                            ✓ Mark Resolved
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($r['status'] !== 'dismissed'): ?>
                    <form method="POST" action="" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="status" value="dismissed">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--text-muted);" onclick="return confirm('Dismiss this report?')">
                            ✕ Dismiss
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($r['status'] !== 'pending'): ?>
                    <form method="POST" action="" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="status" value="pending">
                        <button type="submit" class="btn btn-secondary btn-sm">
                            ↩ Reopen
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($r['question_text']): ?>
                    <form method="POST" action="" style="display: inline; margin-left: auto;" onsubmit="return confirm('WARNING: Are you sure you want to completely delete Question #<?= $r['question_id'] ?>? This cannot be undone.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_question">
                        <input type="hidden" name="question_id" value="<?= $r['question_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">
                            🗑️ Delete Question
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Fix Question Modal -->
<div id="fix-question-modal" class="admin-modal-overlay">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <div>
                <h3 class="admin-modal-title" id="fix-modal-title">Fix Question</h3>
                <p class="admin-modal-subtitle" id="fix-modal-subtitle">Update question details and resolve report</p>
            </div>
            <button type="button" class="admin-modal-close" onclick="closeFixModal()">&times;</button>
        </div>
        <form method="POST" action="" class="admin-modal-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="fix_and_resolve">
            <input type="hidden" name="report_id" id="modal-report-id">
            <input type="hidden" name="question_id" id="modal-question-id">

            <div class="admin-form-group">
                <label class="admin-label" for="modal-qtext">Question Text</label>
                <textarea name="question_text" id="modal-qtext" class="admin-input-textarea" rows="3" required></textarea>
            </div>

            <div class="admin-options-grid">
                <div class="admin-form-group">
                    <label class="admin-label" for="modal-opta">Option A</label>
                    <input type="text" name="option_a" id="modal-opta" class="admin-input" required>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label" for="modal-optb">Option B</label>
                    <input type="text" name="option_b" id="modal-optb" class="admin-input" required>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label" for="modal-optc">Option C</label>
                    <input type="text" name="option_c" id="modal-optc" class="admin-input" required>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label" for="modal-optd">Option D</label>
                    <input type="text" name="option_d" id="modal-optd" class="admin-input" required>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group" style="flex: 1;">
                    <label class="admin-label" for="modal-co">Correct Option</label>
                    <select name="correct_option" id="modal-co" class="admin-select" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <div class="admin-form-group" style="flex: 2;">
                    <label class="admin-label" for="modal-exp">Explanation</label>
                    <textarea name="explanation" id="modal-exp" class="admin-input-textarea" rows="2" placeholder="Correct explanation"></textarea>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label" for="modal-notes">Admin Notes / Resolution Summary</label>
                <input type="text" name="admin_notes" id="modal-notes" class="admin-input" placeholder="e.g. Corrected Option B to Newton; updated explanation.">
            </div>

            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeFixModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save & Mark Resolved</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFixModal(report) {
    document.getElementById('fix-modal-title').textContent = 'Fix Question #' + report.question_id;
    document.getElementById('fix-modal-subtitle').textContent = 'Report #' + report.id + ' — ' + (report.reason.replace('_', ' ').toUpperCase());
    document.getElementById('modal-report-id').value = report.id;
    document.getElementById('modal-question-id').value = report.question_id;
    document.getElementById('modal-qtext').value = report.question_text || '';
    document.getElementById('modal-opta').value = report.option_a || '';
    document.getElementById('modal-optb').value = report.option_b || '';
    document.getElementById('modal-optc').value = report.option_c || '';
    document.getElementById('modal-optd').value = report.option_d || '';
    document.getElementById('modal-co').value = report.correct_option || 'A';
    document.getElementById('modal-exp').value = report.explanation || '';
    document.getElementById('modal-notes').value = 'Resolved: corrected question text/options.';
    document.getElementById('fix-question-modal').style.display = 'flex';
}

function closeFixModal() {
    document.getElementById('fix-question-modal').style.display = 'none';
}

// Close on backdrop click
document.getElementById('fix-question-modal').addEventListener('click', function(e) {
    if (e.target === this) closeFixModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
