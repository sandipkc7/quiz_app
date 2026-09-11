<?php
/**
 * API: Submit Answer
 * Receives: session_id, question_id, selected_option, csrf_token
 * Returns JSON: { correct, correct_option, explanation }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

// Must be logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);

$session_id      = intval($input['session_id'] ?? 0);
$question_id     = intval($input['question_id'] ?? 0);
$selected_option = strtoupper(trim($input['selected_option'] ?? ''));
$csrf             = $input['csrf_token'] ?? '';

// Validate CSRF
if (!csrf_verify($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Validate input
if ($session_id <= 0 || $question_id <= 0 || !in_array($selected_option, ['A', 'B', 'C', 'D'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Validate session belongs to current user
$db = getDB();
$stmt = $db->prepare("SELECT id FROM quiz_sessions WHERE id = :id AND user_id = :user_id AND completed_at IS NULL");
$stmt->execute(['id' => $session_id, 'user_id' => $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

// Check if already answered
$stmt = $db->prepare("SELECT id FROM quiz_answers WHERE session_id = :session_id AND question_id = :question_id");
$stmt->execute(['session_id' => $session_id, 'question_id' => $question_id]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Question already answered']);
    exit;
}

// Get the correct answer
$stmt = $db->prepare("SELECT correct_option, explanation FROM questions WHERE id = :id");
$stmt->execute(['id' => $question_id]);
$question = $stmt->fetch();

if (!$question) {
    http_response_code(404);
    echo json_encode(['error' => 'Question not found']);
    exit;
}

$is_correct = ($selected_option === $question['correct_option']) ? 1 : 0;

// Record the answer
$stmt = $db->prepare("
    INSERT INTO quiz_answers (session_id, question_id, selected_option, is_correct)
    VALUES (:session_id, :question_id, :selected, :correct)
");
$stmt->execute([
    'session_id'  => $session_id,
    'question_id' => $question_id,
    'selected'    => $selected_option,
    'correct'     => $is_correct
]);

// Update score if correct (no negative scoring)
if ($is_correct) {
    $stmt = $db->prepare("UPDATE quiz_sessions SET score = score + 1 WHERE id = :id");
    $stmt->execute(['id' => $session_id]);
}

echo json_encode([
    'correct'        => (bool) $is_correct,
    'correct_option' => $question['correct_option'],
    'explanation'    => $question['explanation'] ?? ''
], JSON_UNESCAPED_UNICODE);
