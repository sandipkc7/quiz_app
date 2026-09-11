<?php
/**
 * Admin: Manage Users & Passwords
 * Allows viewing all registered users and resetting any user's password directly
 */
$page_title = 'Manage Users — Admin';
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
        redirect('/admin/manage_users.php');
    }

    if ($action === 'admin_reset_password') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $new_password   = $_POST['new_password'] ?? '';
        $confirm_pass   = $_POST['confirm_password'] ?? '';

        if ($target_user_id <= 0 || empty($new_password)) {
            flash('error', 'Please enter a valid password.');
        } elseif (strlen($new_password) < 6) {
            flash('error', 'New password must be at least 6 characters long.');
        } elseif ($new_password !== $confirm_pass) {
            flash('error', 'Password and confirmation do not match.');
        } else {
            // Verify target user exists
            $stmt = $db->prepare("SELECT id, username FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $target_user_id]);
            $target_user = $stmt->fetch();

            if (!$target_user) {
                flash('error', 'Target user not found.');
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $stmt->execute(['hash' => $new_hash, 'id' => $target_user_id]);

                flash('success', "Password for user '{$target_user['username']}' has been successfully reset.");
            }
        }
        redirect('/admin/manage_users.php');

    } elseif ($action === 'toggle_role') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $new_role       = $_POST['new_role'] ?? '';

        if ($target_user_id === $current_user['id']) {
            flash('error', 'You cannot change your own administrator role.');
        } elseif ($target_user_id > 0 && in_array($new_role, ['admin', 'user'])) {
            $stmt = $db->prepare("UPDATE users SET role = :role WHERE id = :id");
            $stmt->execute(['role' => $new_role, 'id' => $target_user_id]);

            flash('success', "User role updated to " . strtoupper($new_role) . ".");
        }
        redirect('/admin/manage_users.php');

    } elseif ($action === 'delete_user') {
        $target_user_id = intval($_POST['user_id'] ?? 0);

        if ($target_user_id === $current_user['id']) {
            flash('error', 'You cannot delete your own account.');
        } elseif ($target_user_id > 0) {
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $target_user_id]);

            flash('success', "User #$target_user_id has been removed.");
        }
        redirect('/admin/manage_users.php');
    }
}

// Search
$search = trim($_GET['q'] ?? '');
$search_sql = '';
$params = [];

if (!empty($search)) {
    $search_sql = 'WHERE u.username LIKE :q OR u.email LIKE :q';
    $params['q'] = '%' . $search . '%';
}

$stmt = $db->prepare("
    SELECT u.id, u.username, u.email, u.role, u.created_at,
           COUNT(DISTINCT qs.id) AS total_quizzes,
           COALESCE(SUM(qs.score), 0) AS total_score
    FROM users u
    LEFT JOIN quiz_sessions qs ON qs.user_id = u.id AND qs.completed_at IS NOT NULL
    $search_sql
    GROUP BY u.id
    ORDER BY u.id ASC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$total_user_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<a href="<?= BASE_URL ?>/admin/index.php" class="back-link">← Back to Admin Panel</a>

<div class="page-header flex-between align-center flex-wrap gap-md">
    <div>
        <h1 class="page-title">👥 User Management</h1>
        <p class="page-subtitle">View all registered users and reset passwords (<?= $total_user_count ?> total users)</p>
    </div>
    <form method="GET" action="" style="display: flex; gap: 8px;">
        <input type="text"
               name="q"
               class="form-input"
               placeholder="Search by username or email..."
               value="<?= e($search) ?>"
               style="width: 260px; padding: 8px 12px; font-size: 0.88rem;">
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <?php if (!empty($search)): ?>
            <a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>User</th>
                <th>Email</th>
                <th>Role</th>
                <th>Registered</th>
                <th>Quizzes</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" class="text-center" style="padding: var(--space-2xl);">
                        No users found matching your search.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?= $u['id'] ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="nav-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                <?= strtoupper(substr($u['username'], 0, 1)) ?>
                            </span>
                            <span style="font-weight: 600; color: var(--text-primary);"><?= e($u['username']) ?></span>
                            <?php if ($u['id'] === $current_user['id']): ?>
                                <span class="card-badge" style="font-size: 0.65rem; background: var(--bg-glass);">YOU</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <span class="card-badge <?= $u['role'] === 'admin' ? 'report-badge-pending' : '' ?>" style="font-size: 0.75rem;">
                            <?= strtoupper($u['role']) ?>
                        </span>
                    </td>
                    <td style="font-size: 0.85rem; color: var(--text-secondary);">
                        <?= date('M j, Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td>
                        <span class="card-badge" style="background: var(--bg-glass);">
                            <?= $u['total_quizzes'] ?> taken
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                            <button type="button"
                                    class="btn btn-primary btn-sm"
                                    onclick="openResetModal(<?= $u['id'] ?>, '<?= e(addslashes($u['username'])) ?>')">
                                🔑 Reset Pass
                            </button>

                            <?php if ($u['id'] !== $current_user['id']): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="new_role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Change role to <?= $u['role'] === 'admin' ? 'User' : 'Admin' ?>" onclick="return confirm('Change <?= e($u['username']) ?> role to <?= $u['role'] === 'admin' ? 'User' : 'Admin' ?>?');">
                                        <?= $u['role'] === 'admin' ? 'Demote' : 'Promote' ?>
                                    </button>
                                </form>

                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('WARNING: Are you sure you want to completely delete user \'<?= e($u['username']) ?>\'? All their quizzes and answers will be removed.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete User">
                                        🗑️
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Admin Password Reset Modal -->
<div id="reset-password-modal" class="admin-modal-overlay">
    <div class="admin-modal-content" style="max-width: 480px;">
        <div class="admin-modal-header">
            <div>
                <h3 class="admin-modal-title" id="reset-modal-title">🔑 Reset User Password</h3>
                <p class="admin-modal-subtitle" id="reset-modal-subtitle">Set a new password for this account</p>
            </div>
            <button type="button" class="admin-modal-close" onclick="closeResetModal()">&times;</button>
        </div>
        <form method="POST" action="" class="admin-modal-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_reset_password">
            <input type="hidden" name="user_id" id="reset-user-id">

            <div class="admin-form-group">
                <label class="admin-label" for="admin-new-pass">New Password</label>
                <input type="password"
                       name="new_password"
                       id="admin-new-pass"
                       class="admin-input"
                       placeholder="Minimum 6 characters"
                       minlength="6"
                       required
                       autocomplete="new-password">
            </div>

            <div class="admin-form-group">
                <label class="admin-label" for="admin-confirm-pass">Confirm New Password</label>
                <input type="password"
                       name="confirm_password"
                       id="admin-confirm-pass"
                       class="admin-input"
                       placeholder="Re-enter new password"
                       minlength="6"
                       required
                       autocomplete="new-password">
            </div>

            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save New Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(userId, username) {
    document.getElementById('reset-user-id').value = userId;
    document.getElementById('reset-modal-title').textContent = '🔑 Reset Password for ' + username;
    document.getElementById('reset-modal-subtitle').textContent = 'User ID #' + userId;
    document.getElementById('admin-new-pass').value = '';
    document.getElementById('admin-confirm-pass').value = '';
    document.getElementById('reset-password-modal').style.display = 'flex';
}

function closeResetModal() {
    document.getElementById('reset-password-modal').style.display = 'none';
}

document.getElementById('reset-password-modal').addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
