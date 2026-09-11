<?php
/**
 * Shared HTML Header + Navigation
 * Variables expected: $page_title (optional)
 */
require_once __DIR__ . '/functions.php';
$page_title = isset($page_title) ? $page_title . ' — ' . APP_NAME : APP_NAME;
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="QuizMaster — Test your knowledge with interactive quizzes across multiple subjects and languages.">
    <title><?= e($page_title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : '1.1' ?>">
    <?php if (isset($extra_css)): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= $extra_css ?>?v=<?= file_exists(__DIR__ . '/../assets/css/' . $extra_css) ? filemtime(__DIR__ . '/../assets/css/' . $extra_css) : '1.1' ?>">
    <?php endif; ?>
    <script>
        // Apply saved theme immediately to prevent flash of wrong theme
        (function() {
            const saved = localStorage.getItem('quizmaster-theme');
            if (saved === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            } else if (!saved && window.matchMedia('(prefers-color-scheme: light)').matches) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body class="<?= e($body_class ?? '') ?>">
    <!-- Navigation -->
    <nav class="navbar" id="main-navbar">
        <div class="nav-container">
            <a href="<?= BASE_URL ?>/dashboard.php" class="nav-brand">
                <span class="brand-icon">⚡</span>
                <span class="brand-text"><?= APP_NAME ?></span>
            </a>

            <!-- Theme Toggle (always visible) -->
            <button class="theme-toggle" id="theme-toggle" aria-label="Toggle theme" title="Toggle dark/light theme">
                <span class="icon-moon">🌙</span>
                <span class="icon-sun">☀️</span>
            </button>

            <?php if ($user): ?>
            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">
                <span></span><span></span><span></span>
            </button>
            <div class="nav-menu" id="nav-menu">
                <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link">
                    <span class="nav-icon">📚</span> Subjects
                </a>
                <a href="<?= BASE_URL ?>/history.php" class="nav-link">
                    <span class="nav-icon">📊</span> History
                </a>
                <?php if (is_admin()): ?>
                <?php
                $pending_rep_count = 0;
                try {
                    $db_hdr = getDB();
                    $pending_rep_count = (int) ($db_hdr->query("SELECT COUNT(*) FROM question_reports WHERE status = 'pending'")->fetchColumn() ?: 0);
                } catch (Throwable $e) {
                    $pending_rep_count = 0;
                }
                ?>
                <a href="<?= BASE_URL ?>/admin/index.php" class="nav-link">
                    <span class="nav-icon">⚙️</span> Admin
                    <?php if ($pending_rep_count > 0): ?>
                        <span class="nav-pending-badge" title="<?= $pending_rep_count ?> pending question reports"><?= $pending_rep_count ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <div class="nav-user">
                    <span class="nav-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                    <span class="nav-username"><?= e($user['username']) ?></span>
                    <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-link nav-logout">Logout</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php $flashes = get_flash(); ?>
    <?php if (!empty($flashes)): ?>
    <div class="flash-container" id="flash-container">
        <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>">
            <span class="flash-text"><?= e($flash['message']) ?></span>
            <button class="flash-close" onclick="this.parentElement.remove()">✕</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
