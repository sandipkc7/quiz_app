<?php
/**
 * API: Complete Quiz Session
 * Marks the quiz as completed and returns final score
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$session_id = intval($input['session_id'] ?? 0);
$csrf = $input['csrf_token'] ?? '';

if (!csrf_verify($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$db = getDB();

// Mark session as completed
$stmt = $db->prepare("
    UPDATE quiz_sessions
    SET completed_at = NOW()
    WHERE id = :id AND user_id = :user_id AND completed_at IS NULL
");
$stmt->execute(['id' => $session_id, 'user_id' => $_SESSION['user_id']]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Session not found or already completed']);
    exit;
}

echo json_encode(['success' => true, 'redirect' => BASE_URL . '/result.php?session_id=' . $session_id]);
