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

    // Group all questions by case_study_id
    $case_groups = [];
    foreach ($raw_questions as $q) {
        if (!empty($q['case_study_id'])) {
            $case_groups[$q['case_study_id']][] = $q;
        }
    }

    $ordered = [];
    $processed_case_studies = [];
    $processed_question_ids = [];

    // Walk through questions in their original serial order
    foreach ($raw_questions as $q) {
        $qid = $q['id'];
        if (isset($processed_question_ids[$qid])) {
            continue;
        }

        // If we have already reached or exceeded the limit, stop
        if (count($ordered) >= $limit) {
            break;
        }

        $cs_id = $q['case_study_id'] ?? null;
        if (!empty($cs_id)) {
            if (isset($processed_case_studies[$cs_id])) {
                continue;
            }

            // Output all questions belonging to this case study consecutively
            $cs_questions = $case_groups[$cs_id] ?? [$q];
            $cs_total = count($cs_questions);

            foreach ($cs_questions as $idx => $cq) {
                $cq['case_study_index'] = $idx + 1;
                $cq['case_study_total'] = $cs_total;
                $ordered[] = $cq;
                $processed_question_ids[$cq['id']] = true;
            }

            $processed_case_studies[$cs_id] = true;
        } else {
            // Standalone question
            $ordered[] = $q;
            $processed_question_ids[$qid] = true;
        }
    }

    return $ordered;
}
