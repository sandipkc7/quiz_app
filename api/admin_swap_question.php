<?php
/**
 * API: Admin Swap Question
 * Replaces a question in the current quiz session with an unused question from the chapter/subject
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

// Must be logged in and admin
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized. Admin access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$csrf = $input['csrf_token'] ?? '';
if (!csrf_verify($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$session_id          = intval($input['session_id'] ?? 0);
$current_question_id = intval($input['current_question_id'] ?? 0);
$chapter_id          = intval($input['chapter_id'] ?? 0);
$subject_id          = intval($input['subject_id'] ?? 0);
$existing_ids        = array_map('intval', (array) ($input['existing_ids'] ?? []));

if ($session_id <= 0 || $current_question_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Session ID and Current Question ID are required.']);
    exit;
}

$db = getDB();

// Build exclusion list
if (!in_array($current_question_id, $existing_ids)) {
    $existing_ids[] = $current_question_id;
}
$placeholders = !empty($existing_ids) ? implode(',', $existing_ids) : '0';

// Try finding a replacement question in the current chapter first
$new_question = null;

if ($chapter_id > 0) {
    $stmt = $db->prepare("
        SELECT q.id, q.chapter_id, q.case_study_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
               q.correct_option, q.explanation,
               cs.title AS case_study_title, cs.passage_text
        FROM questions q
        LEFT JOIN case_studies cs ON cs.id = q.case_study_id
        WHERE q.chapter_id = :chapter_id
          AND q.id NOT IN ($placeholders)
        ORDER BY q.id ASC
        LIMIT 1
    ");
    $stmt->execute(['chapter_id' => $chapter_id]);
    $new_question = $stmt->fetch();
}

// If no unused questions in chapter, look across entire subject
if (!$new_question && $subject_id > 0) {
    $stmt = $db->prepare("
        SELECT q.id, q.chapter_id, q.case_study_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
               q.correct_option, q.explanation,
               cs.title AS case_study_title, cs.passage_text
        FROM questions q
        JOIN chapters c ON c.id = q.chapter_id
        LEFT JOIN case_studies cs ON cs.id = q.case_study_id
        WHERE c.subject_id = :subject_id
          AND q.id NOT IN ($placeholders)
        ORDER BY c.sort_order ASC, c.id ASC, q.id ASC
        LIMIT 1
    ");
    $stmt->execute(['subject_id' => $subject_id]);
    $new_question = $stmt->fetch();
}

if (!$new_question) {
    http_response_code(404);
    echo json_encode(['error' => 'No other unused questions available in this chapter or subject to swap with.']);
    exit;
}

// If the current question was previously answered, remove the answer and decrement score if it was correct
$stmt = $db->prepare("SELECT is_correct FROM quiz_answers WHERE session_id = :sid AND question_id = :qid");
$stmt->execute(['sid' => $session_id, 'qid' => $current_question_id]);
$existing_ans = $stmt->fetch();

if ($existing_ans) {
    if ($existing_ans['is_correct']) {
        $stmt = $db->prepare("UPDATE quiz_sessions SET score = GREATEST(0, score - 1) WHERE id = :sid");
        $stmt->execute(['sid' => $session_id]);
    }
    $stmt = $db->prepare("DELETE FROM quiz_answers WHERE session_id = :sid AND question_id = :qid");
    $stmt->execute(['sid' => $session_id, 'qid' => $current_question_id]);
}

// Update session question ids in $_SESSION
if (isset($_SESSION['quiz_question_ids'])) {
    $idx = array_search($current_question_id, $_SESSION['quiz_question_ids']);
    if ($idx !== false) {
        $_SESSION['quiz_question_ids'][$idx] = $new_question['id'];
    }
}

echo json_encode([
    'success'      => true,
    'message'      => 'Question swapped successfully',
    'new_question' => $new_question
]);
