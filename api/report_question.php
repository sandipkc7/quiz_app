<?php
/**
 * API: Report Question
 * Allows logged-in users to report a question with specific issues:
 *   - wrong_answer
 *   - incomplete
 *   - irrelevant
 *   - other
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to report a question.']);
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

$question_id = intval($input['question_id'] ?? 0);
$reason      = trim($input['reason'] ?? '');
$details     = trim($input['details'] ?? '');

$allowed_reasons = ['wrong_answer', 'incomplete', 'irrelevant', 'other'];

if ($question_id <= 0 || !in_array($reason, $allowed_reasons)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please select a valid report reason.']);
    exit;
}

$db = getDB();

// Verify question exists
$stmt = $db->prepare("SELECT id FROM questions WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $question_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Question not found.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check for existing pending report by this user
$stmt = $db->prepare("
    SELECT id FROM question_reports
    WHERE user_id = :uid AND question_id = :qid AND status = 'pending'
    LIMIT 1
");
$stmt->execute(['uid' => $user_id, 'qid' => $question_id]);
if ($stmt->fetch()) {
    echo json_encode([
        'success' => false,
        'error'   => 'You have already submitted a report for this question that is pending review.'
    ]);
    exit;
}

// Insert report
$stmt = $db->prepare("
    INSERT INTO question_reports (user_id, question_id, reason, details, status)
    VALUES (:uid, :qid, :reason, :details, 'pending')
");
$stmt->execute([
    'uid'     => $user_id,
    'qid'     => $question_id,
    'reason'  => $reason,
    'details' => !empty($details) ? $details : null
]);

echo json_encode([
    'success' => true,
    'message' => 'Thank you! Your report has been submitted to administrators for review.'
]);
