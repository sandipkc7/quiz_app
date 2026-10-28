<?php
/**
 * Reset Password Page
 * Validates reset token and allows updating to a new password
 */
$page_title = 'Reset Password';
$extra_css = 'auth.css';

require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect('/dashboard.php');
}

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = '';
$is_valid = false;
$invalid_reason = '';
$reset_record = null;

if (empty($token)) {
    $invalid_reason = 'No reset token was provided. Please use the link sent to your email or request a new one.';
} else {
    $db = getDB();
    $token_hash = hash('sha256', $token);

    $stmt = $db->prepare("
        SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, pr.used_at,
               u.username, u.email
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = :token_hash
        LIMIT 1
    ");
    $stmt->execute(['token_hash' => $token_hash]);
    $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset_record) {
        $invalid_reason = 'This password reset link is invalid or does not exist. Please request a new one.';
    } elseif (!empty($reset_record['used_at'])) {
        $invalid_reason = 'This password reset link has already been used. Please request a new link if you need to reset again.';
    } elseif (strtotime($reset_record['expires_at']) < time()) {
        $invalid_reason = 'This password reset link has expired. Password reset links are valid for 60 minutes.';
    } else {
        $is_valid = true;
    }
}

// Handle form submission
if ($is_valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (empty($password) || empty($confirm)) {
            $error = 'Please fill in all password fields.';
        } elseif (strlen($password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match. Please verify and try again.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Update user password hash
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateUser = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :uid");
                $updateUser->execute([
                    'hash' => $newHash,
                    'uid'  => $reset_record['user_id']
                ]);

                // 2. Mark this token (and any other pending tokens for this user) as used
                $markUsed = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL");
                $markUsed->execute(['uid' => $reset_record['user_id']]);

                $db->commit();

                // Flash success and redirect to login
                flash('success', 'Your password has been successfully reset. Please sign in with your new password.');
                redirect('/auth/login.php');
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'An error occurred while updating your password. Please try again.';
                error_log("Password reset error: " . $e->getMessage());
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-logo">🔑</span>
            <h1 class="auth-title">New Password</h1>
            <?php if ($is_valid): ?>
                <p class="auth-subtitle">Create a secure new password for <strong><?= e($reset_record['username']) ?></strong></p>
            <?php else: ?>
                <p class="auth-subtitle">Password reset link status</p>
            <?php endif; ?>
        </div>

        <?php if (!$is_valid): ?>
            <div class="auth-info-card">
                <span class="info-icon">⚠️</span>
                <p><strong>Link Unavailable</strong></p>
                <p><?= e($invalid_reason) ?></p>
            </div>

            <div style="display: flex; flex-direction: column; gap: var(--space-md); text-align: center;">
                <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="auth-btn" style="text-decoration: none;">
                    Request New Reset Link
                </a>
                <a href="<?= BASE_URL ?>/auth/login.php" class="auth-forgot-link" style="padding: var(--space-sm);">
                    Return to Sign In
                </a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="auth-error" id="reset-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="reset-password-form">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="auth-input-group">
                    <div class="password-input-wrapper">
                        <input type="password"
                               class="auth-input"
                               id="password-input"
                               name="password"
                               placeholder=" "
                               required
                               minlength="6"
                               autocomplete="new-password">
                        <label class="auth-label" for="password-input">New Password</label>
                        <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('password-input', this)" aria-label="Toggle password visibility">
                            👁️
                        </button>
                    </div>
                </div>

                <div class="auth-input-group">
                    <div class="password-input-wrapper">
                        <input type="password"
                               class="auth-input"
                               id="confirm-input"
                               name="password_confirm"
                               placeholder=" "
                               required
                               minlength="6"
                               autocomplete="new-password">
                        <label class="auth-label" for="confirm-input">Confirm New Password</label>
                        <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('confirm-input', this)" aria-label="Toggle confirm password visibility">
                            👁️
                        </button>
                    </div>
                </div>

                <button type="submit" class="auth-btn" id="save-password-btn">Update Password</button>
            </form>

            <div class="auth-footer">
                Remember your existing password? <a href="<?= BASE_URL ?>/auth/login.php">Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
