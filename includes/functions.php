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

/**
 * Smart Question Selection Engine:
 * Fetches questions for a user in a chapter or subject, prioritizing:
 *  1. Unattempted / new questions
 *  2. Incorrectly answered questions (weak areas)
 *  3. Least-recently practiced questions (circular rotation)
 * Also ensures case study sibling questions stay contiguous.
 */
function getQuizQuestionsForUser(PDO $db, int $user_id, ?int $chapter_id = null, ?int $subject_id = null, int $limit = 20): array {
    if (!$chapter_id && !$subject_id) {
        return [];
    }

    // 1. Fetch user's question answer history
    $history_stmt = $db->prepare("
        SELECT qa.question_id,
               MAX(qa.is_correct) AS ever_correct,
               MAX(qa.id) AS last_answer_id,
               COUNT(qa.id) AS times_answered
        FROM quiz_answers qa
        JOIN quiz_sessions qs ON qs.id = qa.session_id
        WHERE qs.user_id = :user_id
        GROUP BY qa.question_id
    ");
    $history_stmt->execute(['user_id' => $user_id]);
    $history_map = [];
    while ($row = $history_stmt->fetch(PDO::FETCH_ASSOC)) {
        $history_map[$row['question_id']] = $row;
    }

    // 2. Fetch primary pool of questions
    if ($chapter_id > 0) {
        $stmt = $db->prepare("
            SELECT q.id, q.chapter_id, q.case_study_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                   q.correct_option, q.explanation,
                   cs.title AS case_study_title, cs.passage_text
            FROM questions q
            LEFT JOIN case_studies cs ON cs.id = q.case_study_id
            WHERE q.chapter_id = :chapter_id
            ORDER BY q.id ASC
        ");
        $stmt->execute(['chapter_id' => $chapter_id]);
        $primary_pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("
            SELECT q.id, q.chapter_id, q.case_study_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                   q.correct_option, q.explanation,
                   cs.title AS case_study_title, cs.passage_text
            FROM questions q
            JOIN chapters c ON c.id = q.chapter_id
            LEFT JOIN case_studies cs ON cs.id = q.case_study_id
            WHERE c.subject_id = :subject_id
            ORDER BY c.sort_order ASC, c.id ASC, q.id ASC
        ");
        $stmt->execute(['subject_id' => $subject_id]);
        $primary_pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Helper to partition questions into 3 tiers
    $partitionPool = function(array $pool) use ($history_map) {
        $tier_unanswered = [];
        $tier_wrong = [];
        $tier_practiced = [];

        foreach ($pool as $q) {
            $qid = $q['id'];
            if (!isset($history_map[$qid])) {
                $tier_unanswered[] = $q;
            } else {
                $h = $history_map[$qid];
                if ((int)$h['ever_correct'] === 0) {
                    $tier_wrong[] = $q;
                } else {
                    $q['_last_answer_id'] = (int)$h['last_answer_id'];
                    $tier_practiced[] = $q;
                }
            }
        }

        // Sort practiced by last_answer_id ascending (oldest practice first)
        usort($tier_practiced, function($a, $b) {
            return ($a['_last_answer_id'] ?? 0) <=> ($b['_last_answer_id'] ?? 0);
        });

        return array_merge($tier_unanswered, $tier_wrong, $tier_practiced);
    };

    $sorted_primary = $partitionPool($primary_pool);

    // If chapter mode has fewer than $limit questions in total, supplement from same subject
    if ($chapter_id > 0 && count($sorted_primary) < $limit && $subject_id > 0) {
        $existing_ids = array_column($sorted_primary, 'id');
        $placeholders = !empty($existing_ids) ? implode(',', $existing_ids) : '0';

        $supp_stmt = $db->prepare("
            SELECT q.id, q.chapter_id, q.case_study_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                   q.correct_option, q.explanation,
                   cs.title AS case_study_title, cs.passage_text
            FROM questions q
            JOIN chapters c ON c.id = q.chapter_id
            LEFT JOIN case_studies cs ON cs.id = q.case_study_id
            WHERE c.subject_id = :subject_id
              AND q.id NOT IN ($placeholders)
            ORDER BY c.sort_order ASC, c.id ASC, q.id ASC
        ");
        $supp_stmt->execute(['subject_id' => $subject_id]);
        $supp_pool = $supp_stmt->fetchAll(PDO::FETCH_ASSOC);
        $sorted_supp = $partitionPool($supp_pool);

        $sorted_primary = array_merge($sorted_primary, $sorted_supp);
    }

    // 3. Ensure all sibling questions for any encountered case study are present
    $cs_ids = array_unique(array_filter(array_column($sorted_primary, 'case_study_id')));
    if (!empty($cs_ids)) {
        $cs_placeholders = implode(',', array_fill(0, count($cs_ids), '?'));
        $cs_stmt = $db->prepare("
            SELECT q.id, q.chapter_id, q.case_study_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                   q.correct_option, q.explanation,
                   cs.title AS case_study_title, cs.passage_text
            FROM questions q
            LEFT JOIN case_studies cs ON cs.id = q.case_study_id
            WHERE q.case_study_id IN ($cs_placeholders)
            ORDER BY q.id ASC
        ");
        $cs_stmt->execute(array_values($cs_ids));
        $all_cs_questions = $cs_stmt->fetchAll(PDO::FETCH_ASSOC);

        $existing_map = [];
        foreach ($sorted_primary as $idx => $q) {
            $existing_map[$q['id']] = $idx;
        }

        foreach ($all_cs_questions as $csq) {
            if (!isset($existing_map[$csq['id']])) {
                $sorted_primary[] = $csq;
                $existing_map[$csq['id']] = count($sorted_primary) - 1;
            }
        }
    }

    // 4. Return grouped contiguous questions up to the limit
    return groupAndOrderQuestions($sorted_primary, $limit);
}

