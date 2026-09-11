<?php
/**
 * Registration Page
 */
$page_title = 'Register';
$extra_css = 'auth.css';

require_once __DIR__ . '/../includes/functions.php';

// If already logged in, go to dashboard
if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';
$old = ['username' => '', 'email' => ''];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        $old = ['username' => $username, 'email' => $email];

        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = 'Username must be between 3 and 50 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $db = getDB();

            // Check for existing username or email
            $stmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
            $stmt->execute(['username' => $username, 'email' => $email]);

            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Create user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :hash, 'user')");
                $stmt->execute([
                    'username' => $username,
                    'email'    => $email,
                    'hash'     => $hash
                ]);

                // Auto-login
                $userId = $db->lastInsertId();
                $_SESSION['user_id']   = $userId;
                $_SESSION['username']  = $username;
                $_SESSION['user_role'] = 'user';

                flash('success', 'Account created successfully! Welcome, ' . $username . '!');
                redirect('/dashboard.php');
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-logo">🚀</span>
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Join QuizMaster and start learning today</p>
        </div>

        <?php if ($error): ?>
            <div class="auth-error" id="register-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="register-form">
            <?= csrf_field() ?>

            <div class="auth-input-group">
                <input type="text"
                       class="auth-input"
                       id="username-input"
                       name="username"
                       placeholder=" "
                       value="<?= e($old['username']) ?>"
                       required
                       minlength="3"
                       maxlength="50"
                       autocomplete="username">
                <label class="auth-label" for="username-input">Username</label>
            </div>

            <div class="auth-input-group">
                <input type="email"
                       class="auth-input"
                       id="email-input"
                       name="email"
                       placeholder=" "
                       value="<?= e($old['email']) ?>"
                       required
                       autocomplete="email">
                <label class="auth-label" for="email-input">Email Address</label>
            </div>

            <div class="auth-input-group">
                <input type="password"
                       class="auth-input"
                       id="password-input"
                       name="password"
                       placeholder=" "
                       required
                       minlength="6"
                       autocomplete="new-password">
                <label class="auth-label" for="password-input">Password</label>
            </div>

            <div class="auth-input-group">
                <input type="password"
                       class="auth-input"
                       id="password-confirm-input"
                       name="password_confirm"
                       placeholder=" "
                       required
                       minlength="6"
                       autocomplete="new-password">
                <label class="auth-label" for="password-confirm-input">Confirm Password</label>
            </div>

            <button type="submit" class="auth-btn" id="register-btn">Create Account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Sign in</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
