<?php
/**
 * Login Page
 */
$page_title = 'Login';
$extra_css = 'auth.css';

require_once __DIR__ . '/../includes/functions.php';

// If already logged in, go to dashboard
if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, username, email, password_hash, role FROM users WHERE username = :login_user OR email = :login_email LIMIT 1");
            $stmt->execute([
                'login_user'  => $login,
                'login_email' => $login,
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['user_role'] = $user['role'];

                flash('success', 'Welcome back, ' . $user['username'] . '!');
                redirect('/dashboard.php');
            } else {
                $error = 'Invalid username/email or password.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-logo">⚡</span>
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to continue your learning journey</p>
        </div>

        <?php if ($error): ?>
            <div class="auth-error" id="login-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="login-form">
            <?= csrf_field() ?>

            <div class="auth-input-group">
                <input type="text"
                       class="auth-input"
                       id="login-input"
                       name="login"
                       placeholder=" "
                       value="<?= e($_POST['login'] ?? '') ?>"
                       required
                       autocomplete="username">
                <label class="auth-label" for="login-input">Username or Email</label>
            </div>

            <div class="auth-input-group">
                <input type="password"
                       class="auth-input"
                       id="password-input"
                       name="password"
                       placeholder=" "
                       required
                       autocomplete="current-password">
                <label class="auth-label" for="password-input">Password</label>
            </div>

            <button type="submit" class="auth-btn" id="login-btn">Sign In</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="<?= BASE_URL ?>/auth/register.php">Create one</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
