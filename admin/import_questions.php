<?php
/**
 * Admin — Bulk Question Import (AI & CSV Friendly)
 */
$page_title = 'Import Questions';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = getDB();
$error = '';
$success = '';
$imported_count = 0;

// Fetch all chapters grouped by subject for selection
$chapters_stmt = $db->query("
    SELECT c.id, c.name, s.name AS subject_name, s.icon AS subject_icon
    FROM chapters c
    JOIN subjects s ON s.id = c.subject_id
    ORDER BY s.name ASC, c.sort_order ASC, c.name ASC
");
$all_chapters = $chapters_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $chapter_id = intval($_POST['chapter_id'] ?? 0);
        $raw_input = trim($_POST['json_data'] ?? '');
        
        // Handle file upload if present
        if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $raw_input = file_get_contents($_FILES['file_upload']['tmp_name']);
        }

        if ($chapter_id <= 0) {
            $error = 'Please select a target chapter.';
        } elseif (empty($raw_input)) {
            $error = 'Please paste questions data or upload a JSON/CSV file.';
        } else {
            $questions_to_insert = [];

            // Attempt JSON parse first
            $json_decoded = json_decode($raw_input, true);
            if (is_array($json_decoded)) {
                // If it's wrapped in a root object like {"questions": [...]}
                if (isset($json_decoded['questions']) && is_array($json_decoded['questions'])) {
                    $json_decoded = $json_decoded['questions'];
                }

                foreach ($json_decoded as $item) {
                    $q_text = trim($item['question'] ?? $item['question_text'] ?? '');
                    $oa = trim($item['option_a'] ?? '');
                    $ob = trim($item['option_b'] ?? '');
                    $oc = trim($item['option_c'] ?? '');
                    $od = trim($item['option_d'] ?? '');
                    $co = strtoupper(trim($item['correct_option'] ?? ''));
                    $exp = trim($item['explanation'] ?? '');

                    if ($q_text && $oa && $ob && $oc && $od && in_array($co, ['A', 'B', 'C', 'D'])) {
                        $questions_to_insert[] = [
                            'question_text'  => $q_text,
                            'option_a'       => $oa,
                            'option_b'       => $ob,
                            'option_c'       => $oc,
                            'option_d'       => $od,
                            'correct_option' => $co,
                            'explanation'    => $exp ?: null
                        ];
                    }
                }
            } else {
                // Try CSV parse
                $lines = explode("\n", $raw_input);
                $is_first = true;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    $row = str_getcsv($line);
                    if ($is_first) {
                        $is_first = false;
                        // Skip header row if matches
                        if (strtolower($row[0] ?? '') === 'question' || strtolower($row[0] ?? '') === 'question_text') {
                            continue;
                        }
                    }
                    if (count($row) >= 6) {
                        $q_text = trim($row[0]);
                        $oa = trim($row[1]);
                        $ob = trim($row[2]);
                        $oc = trim($row[3]);
                        $od = trim($row[4]);
                        $co = strtoupper(trim($row[5]));
                        $exp = isset($row[6]) ? trim($row[6]) : null;

                        if ($q_text && $oa && $ob && $oc && $od && in_array($co, ['A', 'B', 'C', 'D'])) {
                            $questions_to_insert[] = [
                                'question_text'  => $q_text,
                                'option_a'       => $oa,
                                'option_b'       => $ob,
                                'option_c'       => $oc,
                                'option_d'       => $od,
                                'correct_option' => $co,
                                'explanation'    => $exp ?: null
                            ];
                        }
                    }
                }
            }

            if (empty($questions_to_insert)) {
                $error = 'Could not parse any valid questions. Please ensure the format matches the standard schema.';
            } else {
                try {
                    $db->beginTransaction();
                    $insert_stmt = $db->prepare("
                        INSERT INTO questions (chapter_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation)
                        VALUES (:chapter_id, :question_text, :option_a, :option_b, :option_c, :option_d, :correct_option, :explanation)
                    ");

                    foreach ($questions_to_insert as $q) {
                        $insert_stmt->execute([
                            'chapter_id'     => $chapter_id,
                            'question_text'  => $q['question_text'],
                            'option_a'       => $q['option_a'],
                            'option_b'       => $q['option_b'],
                            'option_c'       => $q['option_c'],
                            'option_d'       => $q['option_d'],
                            'correct_option' => $q['correct_option'],
                            'explanation'    => $q['explanation'],
                        ]);
                        $imported_count++;
                    }

                    $db->commit();
                    $success = "Successfully imported {$imported_count} questions into the selected chapter!";
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<a href="<?= BASE_URL ?>/admin/index.php" class="back-link">← Back to Admin Panel</a>

<div class="page-header">
    <h1 class="page-title">📥 Bulk Question Import (AI Friendly)</h1>
    <p class="page-subtitle">Quickly feed questions generated by ChatGPT, Claude, or Gemini in JSON or CSV</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" style="padding: 12px; border-radius: var(--radius-sm); margin-bottom: var(--space-lg); background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" style="padding: 12px; border-radius: var(--radius-sm); margin-bottom: var(--space-lg); background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl); align-items: start;">
    <!-- Import Form -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: var(--space-md);">Import Form</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group" style="margin-bottom: var(--space-md);">
                <label style="display: block; font-weight: 600; margin-bottom: var(--space-xs); color: var(--text-primary);">
                    Select Destination Chapter <span style="color: var(--color-danger)">*</span>
                </label>
                <select name="chapter_id" required style="width: 100%; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">
                    <option value="">-- Choose Chapter --</option>
                    <?php 
                    $current_group = '';
                    foreach ($all_chapters as $c): 
                        if ($c['subject_name'] !== $current_group) {
                            if ($current_group !== '') echo '</optgroup>';
                            $current_group = $c['subject_name'];
                            echo '<optgroup label="' . e($c['subject_icon'] . ' ' . $c['subject_name']) . '">';
                        }
                    ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                    <?php if ($current_group !== '') echo '</optgroup>'; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: var(--space-md);">
                <label style="display: block; font-weight: 600; margin-bottom: var(--space-xs); color: var(--text-primary);">
                    Paste JSON / CSV Data
                </label>
                <textarea name="json_data" rows="12" placeholder='[&#10;  {&#10;    "question": "What is ...?",&#10;    "option_a": "Option 1",&#10;    "option_b": "Option 2",&#10;    "option_c": "Option 3",&#10;    "option_d": "Option 4",&#10;    "correct_option": "A",&#10;    "explanation": "Because..."&#10;  }&#10;]' style="width: 100%; padding: 10px; font-family: monospace; font-size: 0.9rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);"></textarea>
            </div>

            <div class="form-group" style="margin-bottom: var(--space-lg);">
                <label style="display: block; font-weight: 600; margin-bottom: var(--space-xs); color: var(--text-primary);">
                    Or Upload File (.json, .csv)
                </label>
                <input type="file" name="file_upload" accept=".json,.csv,.txt" style="color: var(--text-secondary);">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                🚀 Import Questions
            </button>
        </form>
    </div>

    <!-- AI Prompting Reference Guide -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: var(--space-md);">🤖 Standard AI Prompt Template</h2>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: var(--space-md);">
            Copy and paste this prompt into ChatGPT, Claude, or Gemini to get instantly compatible questions:
        </p>

        <div style="position: relative;">
            <pre id="prompt-sample" style="background: var(--bg-secondary); padding: var(--space-md); border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 0.82rem; overflow-x: auto; color: var(--text-primary); line-height: 1.4;">Generate 20 multiple choice questions about [TOPIC OR CHAPTER HERE].
Output strictly valid JSON as an array with no markdown wrappers or intro text, matching this exact schema:

[
  {
    "question": "Question text in English or any Unicode script like Devanagari",
    "option_a": "First option",
    "option_b": "Second option",
    "option_c": "Third option",
    "option_d": "Fourth option",
    "correct_option": "A",
    "explanation": "Brief explanation of why A is correct"
  }
]</pre>
            <button onclick="navigator.clipboard.writeText(document.getElementById('prompt-sample').innerText); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy Prompt', 2000);" class="btn btn-secondary" style="margin-top: var(--space-sm); font-size: 0.8rem; padding: 6px 12px;">
                📋 Copy AI Prompt
            </button>
        </div>

        <div style="margin-top: var(--space-xl);">
            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: var(--space-xs);">CSV Format Alternative</h3>
            <p style="color: var(--text-muted); font-size: 0.82rem; margin-bottom: var(--space-sm);">
                Columns: <code>question, option_a, option_b, option_c, option_d, correct_option, explanation</code>
            </p>
            <pre style="background: var(--bg-secondary); padding: var(--space-sm); border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 0.78rem; color: var(--text-muted); overflow-x: auto;">"What is H2O?","Water","Salt","Oxygen","Gold","A","H2O is chemical formula of water"</pre>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
