<?php
/**
 * API Endpoint — Reset Quiz History & Chapter Progression
 * Deletes quiz_sessions and cascaded quiz_answers for the authenticated user.
 */
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Get JSON input or Form POST
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
if (!$input) {
    $input = $_POST;
}

$csrf_token = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_verify($csrf_token)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token']);
    exit;
}

$user_id = current_user_id();
$scope = trim($input['scope'] ?? 'chapter');
$db = getDB();

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || !empty($input['is_ajax']);

try {
    $deleted_count = 0;

    if ($scope === 'chapter') {
        $chapter_id = intval($input['chapter_id'] ?? 0);
        if ($chapter_id <= 0) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Valid chapter_id is required']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM quiz_sessions WHERE user_id = :user_id AND chapter_id = :chapter_id");
        $stmt->execute(['user_id' => $user_id, 'chapter_id' => $chapter_id]);
        $deleted_count = $stmt->rowCount();

        $message = "Chapter progress reset successfully. Next quiz will start fresh from Question 1.";

    } elseif ($scope === 'subject') {
        $subject_id = intval($input['subject_id'] ?? 0);
        if ($subject_id <= 0) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Valid subject_id is required']);
            exit;
        }

        $stmt = $db->prepare("
            DELETE FROM quiz_sessions 
            WHERE user_id = :user_id 
              AND chapter_id IN (SELECT id FROM chapters WHERE subject_id = :subject_id)
        ");
        $stmt->execute(['user_id' => $user_id, 'subject_id' => $subject_id]);
        $deleted_count = $stmt->rowCount();

        $message = "Subject progress reset successfully for all chapters.";

    } elseif ($scope === 'all') {
        $stmt = $db->prepare("DELETE FROM quiz_sessions WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $deleted_count = $stmt->rowCount();

        $message = "All quiz history and statistics have been reset.";

    } else {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Invalid scope']);
        exit;
    }

    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'deleted_sessions' => $deleted_count
        ]);
        exit;
    } else {
        flash('success', $message);
        $redirect_to = $input['redirect_to'] ?? '/dashboard.php';
        // Validate redirect to prevent open redirects
        if (!str_starts_with($redirect_to, '/') || str_starts_with($redirect_to, '//')) {
            $redirect_to = '/dashboard.php';
        }
        redirect($redirect_to);
    }

} catch (Exception $e) {
    error_log("Error resetting quiz history: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Database error occurred during reset']);
    exit;
}
