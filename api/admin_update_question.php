<?php
/**
 * API: Admin Update Question
 * Allows an admin to edit a question's text, options, correct answer, and explanation live
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

$question_id    = intval($input['question_id'] ?? 0);
$question_text  = trim($input['question_text'] ?? '');
$option_a       = trim($input['option_a'] ?? '');
$option_b       = trim($input['option_b'] ?? '');
$option_c       = trim($input['option_c'] ?? '');
$option_d       = trim($input['option_d'] ?? '');
$correct_option = strtoupper(trim($input['correct_option'] ?? ''));
$explanation    = trim($input['explanation'] ?? '');

if ($question_id <= 0 || empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
    http_response_code(400);
    echo json_encode(['error' => 'Question text and all 4 options are required.']);
    exit;
}

if (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Correct option must be A, B, C, or D.']);
    exit;
}

$db = getDB();

// Ensure question exists
$stmt = $db->prepare("SELECT id FROM questions WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $question_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Question not found']);
    exit;
}

// Update question
$stmt = $db->prepare("
    UPDATE questions
    SET question_text = :question_text,
        option_a = :option_a,
        option_b = :option_b,
        option_c = :option_c,
        option_d = :option_d,
        correct_option = :correct_option,
        explanation = :explanation
    WHERE id = :id
");
$stmt->execute([
    'id'             => $question_id,
    'question_text'  => $question_text,
    'option_a'       => $option_a,
    'option_b'       => $option_b,
    'option_c'       => $option_c,
    'option_d'       => $option_d,
    'correct_option' => $correct_option,
    'explanation'    => $explanation
]);

echo json_encode([
    'success'  => true,
    'message'  => 'Question updated successfully',
    'question' => [
        'id'             => $question_id,
        'question_text'  => $question_text,
        'option_a'       => $option_a,
        'option_b'       => $option_b,
        'option_c'       => $option_c,
        'option_d'       => $option_d,
        'correct_option' => $correct_option,
        'explanation'    => $explanation
    ]
]);
