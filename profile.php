<?php
/**
 * User Profile & Password Change
 */
$page_title = 'My Profile & Account Settings';
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    flash('error', 'Please log in to view your profile.');
    redirect('/auth/login.php');
}

$db = getDB();
$current_user = current_user();

// Fetch latest user info
$stmt = $db->prepare("SELECT id, username, email, password_hash, role, created_at FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $current_user['id']]);
$user_info = $stmt->fetch();

if (!$user_info) {
    redirect('/auth/logout.php');
}

// User activity stats
$stmt = $db->prepare("
    SELECT COUNT(*) AS total_quizzes,
           COALESCE(SUM(score), 0) AS total_score,
           COALESCE(SUM(total_questions), 0) AS total_questions
    FROM quiz_sessions
    WHERE user_id = :id AND completed_at IS NOT NULL
");
$stmt->execute(['id' => $user_info['id']]);
$user_stats = $stmt->fetch();

$total_quizzes = (int) ($user_stats['total_quizzes'] ?? 0);
$total_score   = (int) ($user_stats['total_score'] ?? 0);
$total_qs      = (int) ($user_stats['total_questions'] ?? 0);
$accuracy      = $total_qs > 0 ? round(($total_score / $total_qs) * 100) : 0;

$password_error = '';

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!csrf_verify($csrf)) {
        flash('error', 'Invalid form submission. Please try again.');
        redirect('/profile.php');
    }

    if ($action === 'change_password') {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            $password_error = 'Please fill in all password fields.';
        } elseif (!password_verify($current_pass, $user_info['password_hash'])) {
            $password_error = 'Your current password is incorrect.';
        } elseif (strlen($new_pass) < 6) {
            $password_error = 'New password must be at least 6 characters long.';
        } elseif ($new_pass !== $confirm_pass) {
            $password_error = 'New password and confirmation do not match.';
        } else {
            // Update password
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmt->execute(['hash' => $new_hash, 'id' => $user_info['id']]);

            flash('success', 'Your password has been successfully updated.');
            redirect('/profile.php');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<a href="<?= BASE_URL ?>/dashboard.php" class="back-link">← Back to Dashboard</a>

<div class="page-header">
    <h1 class="page-title">👤 Account Settings</h1>
    <p class="page-subtitle">Manage your profile details and security credentials</p>
</div>

<div class="profile-layout">
    <!-- User Overview Card -->
    <div class="card profile-info-card">
        <div class="profile-avatar-large">
            <?= strtoupper(substr($user_info['username'], 0, 1)) ?>
        </div>
        <h2 class="profile-name"><?= e($user_info['username']) ?></h2>
        <p class="profile-email"><?= e($user_info['email']) ?></p>

        <div class="profile-badges" style="margin: 12px 0;">
            <span class="card-badge" style="background: var(--bg-glass); border: 1px solid var(--border-color); font-weight: 600;">
                Role: <?= strtoupper($user_info['role']) ?>
            </span>
            <span class="card-badge" style="background: var(--bg-glass); border: 1px solid var(--border-color);">
                Joined <?= date('M Y', strtotime($user_info['created_at'])) ?>
            </span>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 16px 0; width: 100%;">

        <div class="profile-stats-grid">
            <div class="profile-stat-box">
                <span class="profile-stat-num"><?= $total_quizzes ?></span>
                <span class="profile-stat-lbl">Quizzes Taken</span>
            </div>
            <div class="profile-stat-box">
                <span class="profile-stat-num"><?= $total_score ?></span>
                <span class="profile-stat-lbl">Correct Answers</span>
            </div>
            <div class="profile-stat-box">
                <span class="profile-stat-num"><?= $accuracy ?>%</span>
                <span class="profile-stat-lbl">Accuracy</span>
            </div>
        </div>

        <?php if ($total_quizzes > 0): ?>
            <div style="margin-top: var(--space-xl); width: 100%;">
                <form method="POST" action="<?= BASE_URL ?>/api/reset_history.php" onsubmit="return confirm('Reset all your quiz history and accuracy records?\n\nThis will permanently delete all past session records and restart your question progression fresh from Tier 1 (Question 1) across all chapters.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="scope" value="all">
                    <input type="hidden" name="redirect_to" value="/profile.php">
                    <button type="submit" class="btn btn-secondary" style="width: 100%; border-color: rgba(239, 68, 68, 0.4); color: #ef4444; font-size: 0.85rem; padding: 10px;">
                        🗑️ Reset All Quiz History & Progress
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Change Password Card -->
    <div class="card profile-security-card">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
            <span style="font-size: 1.4rem;">🔐</span>
            <h2 style="font-size: var(--font-size-xl); font-weight: 700; color: var(--text-primary); margin: 0;">
                Change Password
            </h2>
        </div>
        <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin-bottom: var(--space-lg);">
            Enter your current password followed by a new secure password of at least 6 characters.
        </p>

        <?php if ($password_error): ?>
            <div class="flash flash-error" style="margin-bottom: var(--space-lg); position: static;">
                <?= e($password_error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="change-password-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group" style="margin-bottom: var(--space-md);">
                <label class="admin-label" for="current_password">Current Password</label>
                <input type="password"
                       name="current_password"
                       id="current_password"
                       class="form-input"
                       placeholder="Enter your current password"
                       required
                       autocomplete="current-password">
            </div>

            <div class="form-group" style="margin-bottom: var(--space-md);">
                <label class="admin-label" for="new_password">New Password</label>
                <input type="password"
                       name="new_password"
                       id="new_password"
                       class="form-input"
                       placeholder="Minimum 6 characters"
                       minlength="6"
                       required
                       autocomplete="new-password">
            </div>

            <div class="form-group" style="margin-bottom: var(--space-xl);">
                <label class="admin-label" for="confirm_password">Confirm New Password</label>
                <input type="password"
                       name="confirm_password"
                       id="confirm_password"
                       class="form-input"
                       placeholder="Re-enter your new password"
                       minlength="6"
                       required
                       autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">
                💾 Update Password
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
