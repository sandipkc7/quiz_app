<?php
/**
 * Utility Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

/**
 * Sanitize output for HTML display
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect(string $path): void {
    header("Location: " . BASE_URL . $path);
    exit;
}

/**
 * Set a flash message
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages
 */
function get_flash(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function is_admin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current user info
 */
function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['user_role']
    ];
}

/**
 * Require authentication — redirect to login if not logged in
 */
function require_auth(): void {
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('/auth/login.php');
    }
}

/**
 * Require admin role
 */
function require_admin(): void {
    require_auth();
    if (!is_admin()) {
        flash('error', 'Access denied. Admin privileges required.');
        redirect('/dashboard.php');
    }
}

/**
 * Group and order questions so case study questions stay contiguous
 */
function groupAndOrderQuestions(array $raw_questions, int $limit = 20): array {
    if (empty($raw_questions)) return [];

    $units = [];
    $case_groups = [];

    foreach ($raw_questions as $q) {
        if (!empty($q['case_study_id'])) {
            $case_groups[$q['case_study_id']][] = $q;
        } else {
            $units[] = [$q];
        }
    }

    foreach ($case_groups as $cs_questions) {
        $units[] = $cs_questions;
    }

    shuffle($units);

    $ordered = [];
    $leftover_standalones = [];

    foreach ($units as $unit) {
        $unit_count = count($unit);
        if (count($ordered) + $unit_count <= $limit) {
            foreach ($unit as $idx => $q) {
                if (!empty($q['case_study_id'])) {
                    $q['case_study_index'] = $idx + 1;
                    $q['case_study_total'] = $unit_count;
                }
                $ordered[] = $q;
            }
        } elseif ($unit_count === 1) {
            $leftover_standalones[] = $unit[0];
        }
    }

    // Fill remaining slots with standalone questions
    while (count($ordered) < $limit && !empty($leftover_standalones)) {
        $ordered[] = array_shift($leftover_standalones);
    }

    // If still under limit (e.g. fewer than limit available), add remaining items
    if (count($ordered) < $limit) {
        $existing_ids = array_column($ordered, 'id');
        foreach ($units as $unit) {
            foreach ($unit as $idx => $q) {
                if (!in_array($q['id'], $existing_ids)) {
                    if (!empty($q['case_study_id'])) {
                        $q['case_study_index'] = $idx + 1;
                        $q['case_study_total'] = count($unit);
                    }
                    $ordered[] = $q;
                    $existing_ids[] = $q['id'];
                    if (count($ordered) >= $limit) break 2;
                }
            }
        }
    }

    return $ordered;
}
