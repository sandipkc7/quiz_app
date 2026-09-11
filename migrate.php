<?php
/**
 * ============================================================
 * QuizMaster — Production Database Migration Runner
 * ============================================================
 * Usage:
 *   php migrate.php          Run all pending migrations
 *   php migrate.php --status View applied and pending migrations
 *   php migrate.php --seed   Run database seed data
 *   php migrate.php --help   Show help and usage options
 * ============================================================
 */

// Safety check: protect web access if accessed over HTTP
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/includes/functions.php';
    if (!is_logged_in() || !is_admin()) {
        http_response_code(403);
        die('Access Denied: Migrations can only be run via CLI or by an authenticated administrator.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

echo "============================================================\n";
echo "  QuizMaster Database Migration Runner\n";
echo "============================================================\n\n";

// 1. Verify .env configuration
$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    echo "[ERROR] .env file not found at: $env_path\n";
    echo "Please copy .env.example to .env and configure your production database credentials.\n";
    exit(1);
}

require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
} catch (Throwable $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Ensure schema_migrations tracking table exists
$db->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(191) PRIMARY KEY,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Fetch already applied migrations
$applied = $db->query("SELECT version, applied_at FROM schema_migrations ORDER BY version ASC")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// 3. Discover migration files
$migrations_dir = __DIR__ . '/migrations';
$migration_files = glob($migrations_dir . '/*.sql');

// Filter out seed files from regular numbered migrations
$migration_files = array_filter($migration_files, function ($file) {
    return !str_contains(basename($file), 'seed');
});
sort($migration_files);

// Parse CLI options
$action = 'up';
$argv_options = $argv ?? [];
if (in_array('--status', $argv_options)) {
    $action = 'status';
} elseif (in_array('--seed', $argv_options)) {
    $action = 'seed';
} elseif (in_array('--help', $argv_options)) {
    $action = 'help';
}

switch ($action) {
    case 'help':
        echo "Options:\n";
        echo "  php migrate.php           Run all pending migrations in sequential order\n";
        echo "  php migrate.php --status  Check migration history and pending status\n";
        echo "  php migrate.php --seed    Run initial database seed data (admin & demo content)\n";
        echo "  php migrate.php --help    Display this help menu\n\n";
        exit(0);

    case 'status':
        echo sprintf("%-35s | %-12s | %s\n", "Migration File", "Status", "Applied At");
        echo str_repeat("-", 75) . "\n";
        foreach ($migration_files as $file) {
            $name = basename($file);
            $is_applied = isset($applied[$name]);
            $status = $is_applied ? "APPLIED" : "PENDING";
            $applied_at = $is_applied ? $applied[$name] : "—";
            echo sprintf("%-35s | %-12s | %s\n", $name, $status, $applied_at);
        }
        echo "\n";
        exit(0);

    case 'seed':
        $seed_file = $migrations_dir . '/seed_initial_data.sql';
        if (!file_exists($seed_file)) {
            echo "[ERROR] Seed file not found: $seed_file\n";
            exit(1);
        }
        echo "[RUNNING] Seeding database using: " . basename($seed_file) . "...\n";
        try {
            $sql = file_get_contents($seed_file);
            $db->exec($sql);
            echo "[OK] Seed data inserted successfully.\n";
            echo "Default Admin: admin@quizapp.com / admin123\n\n";
        } catch (Throwable $e) {
            echo "[ERROR] Seeding failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        exit(0);

    case 'up':
    default:
        $pending_count = 0;
        foreach ($migration_files as $file) {
            $version = basename($file);
            if (isset($applied[$version])) {
                continue;
            }

            $pending_count++;
            echo "--> Applying migration: $version ... ";

            try {
                $sql = file_get_contents($file);
                
                $db->exec($sql);

                // Record version in tracking table
                $stmt = $db->prepare("INSERT INTO schema_migrations (version, applied_at) VALUES (:v, NOW())");
                $stmt->execute(['v' => $version]);

                echo "[OK]\n";
            } catch (Throwable $e) {
                echo "[FAILED]\n";
                echo "[ERROR] Migration failed on '$version':\n" . $e->getMessage() . "\n";
                exit(1);
            }
        }

        if ($pending_count === 0) {
            echo "Database schema is completely up to date. No pending migrations.\n";
        } else {
            echo "\nSuccessfully applied $pending_count migration(s).\n";
        }
        echo "\n";
        break;
}
