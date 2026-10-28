<?php
/**
 * Forgot Password Page
 * Requests password reset link and dispatches via SMTP mail
 */
$page_title = 'Forgot Password';
$extra_css = 'auth.css';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';
$success = false;
$success_message = '';
$dev_link = null;
$submitted_email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $loginInput = trim($_POST['email'] ?? '');
        $submitted_email = $loginInput;

        if (empty($loginInput)) {
            $error = 'Please enter your registered email address or username.';
        } else {
            $db = getDB();

            // Locate user by email or username
            $stmt = $db->prepare("SELECT id, username, email FROM users WHERE email = :email OR username = :username LIMIT 1");
            $stmt->execute([
                'email'    => $loginInput,
                'username' => $loginInput
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Rate-limit check: Has a request been submitted in the last 60 seconds?
                $checkStmt = $db->prepare("SELECT id FROM password_resets WHERE user_id = :uid AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
                $checkStmt->execute(['uid' => $user['id']]);

                if ($checkStmt->fetch()) {
                    $error = 'A password reset link was recently requested. Please check your inbox or wait 60 seconds before trying again.';
                } else {
                    // Invalidate previous pending tokens for this user
                    $invalidateStmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL");
                    $invalidateStmt->execute(['uid' => $user['id']]);

                    // Generate a cryptographically secure token
                    $plain_token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $plain_token);
                    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration

                    // Record reset request in database
                    $insertStmt = $db->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :token_hash, :expires_at)");
                    $insertStmt->execute([
                        'uid'        => $user['id'],
                        'token_hash' => $token_hash,
                        'expires_at' => $expires_at
                    ]);

                    // Build absolute reset URL
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $reset_url = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/auth/reset_password.php?token=' . urlencode($plain_token);

                    // Dispatch email via SMTP
                    $mailResult = send_password_reset_email($user['email'], $user['username'], $reset_url);

                    $success = true;
                    $success_message = 'If an account matches that email address or username, password reset instructions have been sent. Please check your inbox and spam folder.';
                    if (!empty($mailResult['dev_link'])) {
                        $dev_link = $mailResult['dev_link'];
                    }
                }
            } else {
                // To mitigate user enumeration attacks, show a consistent success confirmation
                $success = true;
                $success_message = 'If an account matches that email address or username, password reset instructions have been sent. Please check your inbox and spam folder.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-logo">🔐</span>
            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Enter your email or username to receive recovery instructions</p>
        </div>

        <?php if ($error): ?>
            <div class="auth-error" id="forgot-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-info-card" id="forgot-success-card">
                <span class="info-icon">📬</span>
                <p><strong>Check Your Inbox</strong></p>
                <p><?= e($success_message) ?></p>

                <?php if ($dev_link && defined('APP_ENV') && APP_ENV === 'development'): ?>
                    <div class="auth-dev-box">
                        <strong>Developer Mode Note:</strong><br>
                        Direct reset link: <a href="<?= e($dev_link) ?>"><?= e($dev_link) ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <div style="text-align: center; margin-top: var(--space-xl);">
                <a href="<?= BASE_URL ?>/auth/login.php" class="auth-btn" style="display: block; text-decoration: none; text-align: center;">
                    Return to Sign In
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="forgot-form">
                <?= csrf_field() ?>

                <div class="auth-input-group">
                    <input type="text"
                           class="auth-input"
                           id="email-input"
                           name="email"
                           placeholder=" "
                           value="<?= e($submitted_email) ?>"
                           required
                           autofocus
                           autocomplete="email">
                    <label class="auth-label" for="email-input">Email or Username</label>
                </div>

                <button type="submit" class="auth-btn" id="forgot-btn">Send Reset Link</button>
            </form>

            <div class="auth-footer">
                Remember your password? <a href="<?= BASE_URL ?>/auth/login.php">Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
