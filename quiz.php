<?php
/**
 * Quiz Page — Interactive quiz interface
 * Supports:
 *   ?chapter_id=X  → quiz from a specific chapter
 *   ?subject_id=X  → quiz from all chapters in a subject
 * Falls back to subject-level questions when chapter has < 20 questions
 */
$extra_js = 'quiz.js';
$body_class = 'quiz-page';
require_once __DIR__ . '/includes/auth_guard.php';

$chapter_id = intval($_GET['chapter_id'] ?? 0);
$subject_id = intval($_GET['subject_id'] ?? 0);

$db = getDB();

$chapter = null;
$subject = null;
$quiz_mode = '';  // 'chapter' or 'subject'

// --- Determine quiz mode ---
if ($chapter_id > 0) {
    // Chapter-specific quiz
    $stmt = $db->prepare("
        SELECT c.*, s.name AS subject_name, s.icon AS subject_icon, s.id AS subject_id
        FROM chapters c
        JOIN subjects s ON s.id = c.subject_id
        WHERE c.id = :id LIMIT 1
    ");
    $stmt->execute(['id' => $chapter_id]);
    $chapter = $stmt->fetch();

    if (!$chapter) {
        flash('error', 'Chapter not found.');
        redirect('/dashboard.php');
    }

    $subject_id = $chapter['subject_id'];
    $quiz_mode = 'chapter';

    // Fetch questions serially with case study info
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
    $raw_questions = $stmt->fetchAll();

    // If fewer than 20, supplement from the same subject (other chapters) serially
    if (count($raw_questions) < 20) {
        $existing_ids = array_column($raw_questions, 'id');
        $placeholders = !empty($existing_ids) ? implode(',', $existing_ids) : '0';

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
            LIMIT :needed
        ");
        $stmt->bindValue(':subject_id', $subject_id, PDO::PARAM_INT);
        $stmt->bindValue(':needed', 20 - count($raw_questions), PDO::PARAM_INT);
        $stmt->execute();
        $extra = $stmt->fetchAll();
        $raw_questions = array_merge($raw_questions, $extra);
    }

    // Ensure all sibling questions for any encountered case study are loaded
    $cs_ids = array_unique(array_filter(array_column($raw_questions, 'case_study_id')));
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
        $all_cs_questions = $cs_stmt->fetchAll();

        $existing_raw_ids = array_flip(array_column($raw_questions, 'id'));
        foreach ($all_cs_questions as $csq) {
            if (!isset($existing_raw_ids[$csq['id']])) {
                $raw_questions[] = $csq;
                $existing_raw_ids[$csq['id']] = true;
            }
        }
    }

    $questions = groupAndOrderQuestions($raw_questions, 20);

    $page_title = 'Quiz — ' . $chapter['name'];
    $quiz_label = $chapter['name'];
    $quiz_label_local = $chapter['name_local'] ?? '';
    $subject_icon = $chapter['subject_icon'];
    $subject_name = $chapter['subject_name'];
    $back_url = BASE_URL . '/chapters.php?subject_id=' . $subject_id;
    $back_label = $chapter['subject_name'];

} elseif ($subject_id > 0) {
    // Subject-wide quiz
    $stmt = $db->prepare("SELECT * FROM subjects WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $subject_id]);
    $subject = $stmt->fetch();

    if (!$subject) {
        flash('error', 'Subject not found.');
        redirect('/dashboard.php');
    }

    $quiz_mode = 'subject';

    // Fetch questions serially from all chapters in this subject
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
    $raw_questions = $stmt->fetchAll();

    // Ensure all sibling questions for any encountered case study are loaded
    $cs_ids = array_unique(array_filter(array_column($raw_questions, 'case_study_id')));
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
        $all_cs_questions = $cs_stmt->fetchAll();

        $existing_raw_ids = array_flip(array_column($raw_questions, 'id'));
        foreach ($all_cs_questions as $csq) {
            if (!isset($existing_raw_ids[$csq['id']])) {
                $raw_questions[] = $csq;
                $existing_raw_ids[$csq['id']] = true;
            }
        }
    }

    $questions = groupAndOrderQuestions($raw_questions, 20);

    // Pick the first chapter for session storage
    $first_chapter = $db->prepare("SELECT id FROM chapters WHERE subject_id = :sid ORDER BY sort_order LIMIT 1");
    $first_chapter->execute(['sid' => $subject_id]);
    $chapter_id = $first_chapter->fetchColumn() ?: 0;

    $page_title = 'Quiz — ' . $subject['name'];
    $quiz_label = $subject['name'] . ' (All Chapters)';
    $quiz_label_local = $subject['name_local'] ?? '';
    $subject_icon = $subject['icon'];
    $subject_name = $subject['name'];
    $back_url = BASE_URL . '/chapters.php?subject_id=' . $subject_id;
    $back_label = $subject['name'];

} else {
    flash('error', 'Please select a subject or chapter.');
    redirect('/dashboard.php');
}

// --- Validate questions ---
if (empty($questions)) {
    flash('error', 'No questions available. Please try another chapter or subject.');
    redirect('/dashboard.php');
}

$total = count($questions);

// Strip correct_option and explanation for non-admin users so answers cannot be inspected in client JS
$client_questions = $questions;
if (!is_admin()) {
    foreach ($client_questions as &$cq) {
        unset($cq['correct_option'], $cq['explanation']);
    }
    unset($cq);
}

// --- Create quiz session ---
$stmt = $db->prepare("
    INSERT INTO quiz_sessions (user_id, chapter_id, score, total_questions, started_at)
    VALUES (:user_id, :chapter_id, 0, :total, NOW())
");
$stmt->execute([
    'user_id'    => $current_user['id'],
    'chapter_id' => $chapter_id,
    'total'      => $total
]);
$session_id = $db->lastInsertId();

// Store question IDs in PHP session for validation
$_SESSION['quiz_session_id'] = $session_id;
$_SESSION['quiz_question_ids'] = array_column($questions, 'id');
$_SESSION['quiz_current_index'] = 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="quiz-container" id="quiz-container">
    <!-- Compact Top Bar -->
    <div class="quiz-topbar">
        <a href="<?= $back_url ?>" class="quiz-back-btn" title="Back to <?= e($back_label) ?>">
            ← <?= e($back_label) ?>
        </a>
        <div class="quiz-title-badge">
            <span class="quiz-badge-icon"><?= e($subject_icon) ?></span>
            <span class="quiz-badge-title"><?= e($quiz_label) ?></span>
            <?php if ($quiz_label_local): ?>
                <span class="quiz-badge-local">(<?= e($quiz_label_local) ?>)</span>
            <?php endif; ?>
        </div>
        <div class="quiz-counter" id="progress-count">
            1 / <?= $total ?>
        </div>
    </div>

    <!-- Slim Progress Track -->
    <div class="quiz-progress-track">
        <div class="progress-fill" id="progress-fill"></div>
    </div>

    <!-- Question Area (rendered by JavaScript) -->
    <div id="question-area">
        <div class="spinner"></div>
    </div>
</div>

<!-- Pass data to JavaScript -->
<script>
    const QUIZ_DATA = {
        sessionId: <?= $session_id ?>,
        questions: <?= json_encode($client_questions, JSON_UNESCAPED_UNICODE) ?>,
        totalQuestions: <?= $total ?>,
        baseUrl: '<?= BASE_URL ?>',
        csrfToken: '<?= csrf_token() ?>',
        isAdmin: <?= is_admin() ? 'true' : 'false' ?>,
        chapterId: <?= $chapter_id ?>,
        subjectId: <?= $subject_id ?>
    };
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
