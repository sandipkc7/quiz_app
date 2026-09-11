/**
 * QuizMaster — Quiz Engine (JavaScript)
 * Handles compact question rendering, 2x2 grid, keyboard shortcuts, AJAX answer submission, and quiz completion
 */

document.addEventListener('DOMContentLoaded', () => {
    // Bail if not on quiz page
    if (typeof QUIZ_DATA === 'undefined') return;

    const {sessionId, questions, totalQuestions, baseUrl, csrfToken, isAdmin, chapterId, subjectId} = QUIZ_DATA;

    let currentIndex = 0;
    let score = 0;
    let answered = false;

    // DOM elements
    const questionArea = document.getElementById('question-area');
    const progressFill = document.getElementById('progress-fill');
    const progressCount = document.getElementById('progress-count');

    // Initialize first question
    renderQuestion(currentIndex);

    // Keyboard navigation (1-4 or A-D to answer, Enter/Space to next)
    window.addEventListener('keydown', (e) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;

        const key = e.key.toUpperCase();
        if (!answered) {
            let optionKey = null;
            if (key === 'A' || key === '1') optionKey = 'A';
            else if (key === 'B' || key === '2') optionKey = 'B';
            else if (key === 'C' || key === '3') optionKey = 'C';
            else if (key === 'D' || key === '4') optionKey = 'D';

            if (optionKey) {
                const btn = document.querySelector(`[data-option="${optionKey}"]`);
                if (btn && !btn.classList.contains('disabled')) {
                    btn.click();
                }
            }
        } else {
            if (e.key === 'Enter' || e.key === ' ') {
                const nextBtn = document.getElementById('next-btn');
                const actions = document.getElementById('quiz-actions');
                if (nextBtn && actions && actions.style.display !== 'none') {
                    e.preventDefault();
                    nextBtn.click();
                }
            }
        }
    });

    /**
     * Render a question
     */
    function renderQuestion(index) {
        answered = false;

        const q = questions[index];
        const options = [
            {label: 'A', text: q.option_a},
            {label: 'B', text: q.option_b},
            {label: 'C', text: q.option_c},
            {label: 'D', text: q.option_d}
        ];

        // Update progress
        if (progressCount) progressCount.textContent = `${index + 1} / ${totalQuestions}`;
        if (progressFill) progressFill.style.width = `${((index + 1) / totalQuestions) * 100}%`;

        // Update container class for case study styling
        const quizContainer = document.getElementById('quiz-container');
        const hasCaseStudy = Boolean(q.passage_text);
        if (quizContainer) {
            if (hasCaseStudy) {
                quizContainer.classList.add('has-case-study');
            } else {
                quizContainer.classList.remove('has-case-study');
            }
        }

        // Question Card HTML
        const questionCardHtml = `
            <div class="question-card ${hasCaseStudy ? 'question-case-card' : ''}">
                <div class="question-header">
                    <div class="question-header-meta">
                        <span class="question-number">Question ${index + 1} of ${totalQuestions}</span>
                        ${isAdmin ? `
                            <button type="button" class="btn-admin-manage" id="btn-admin-manage" title="Admin: Edit or Change Question">
                                ⚙️ Edit / Change Question
                            </button>
                        ` : ''}
                    </div>
                    <span class="question-kbd-hint">💡 Keys: [A-D] or [1-4] &bull; [Enter] next</span>
                </div>
                <h2 class="question-text">${escapeHtml(q.question_text)}</h2>
                <div class="options-grid" id="options-grid">
                    ${options.map(opt => `
                        <button class="option-btn" data-option="${opt.label}" id="option-${opt.label}">
                            <span class="option-label">${opt.label}</span>
                            <span class="option-text">${escapeHtml(opt.text)}</span>
                        </button>
                    `).join('')}
                </div>
                <div class="quiz-bottom-row" id="quiz-bottom-row">
                    <div id="explanation-area" class="quiz-explanation-area"></div>
                    <div class="quiz-actions" id="quiz-actions" style="display: none;">
                        <button class="btn btn-primary" id="next-btn">
                            ${index === totalQuestions - 1 ? 'View Results 🎉' : 'Next Question →'}
                        </button>
                    </div>
                </div>
            </div>
        `;

        if (hasCaseStudy) {
            const formattedPassage = escapeHtml(q.passage_text)
                .replace(/\n\n/g, '<br><br>')
                .replace(/\n/g, '<br>');

            questionArea.innerHTML = `
                <div class="quiz-case-split">
                    <div class="case-study-pane" id="case-study-pane">
                        <button type="button" class="case-study-toggle-btn" id="case-study-toggle">
                            📖 Toggle Case Study Passage
                        </button>
                        <div class="case-study-header">
                            <span class="case-study-badge">📖 CASE STUDY</span>
                            ${q.case_study_total ? `<span class="case-study-progress">Question ${q.case_study_index} of ${q.case_study_total}</span>` : ''}
                        </div>
                        <h3 class="case-study-title">${escapeHtml(q.case_study_title || 'Case Scenario')}</h3>
                        <div class="case-study-body">
                            ${formattedPassage}
                        </div>
                    </div>
                    ${questionCardHtml}
                </div>
            `;

            // Mobile toggle handler
            const toggleBtn = document.getElementById('case-study-toggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    const pane = document.getElementById('case-study-pane');
                    if (pane) {
                        pane.classList.toggle('collapsed');
                    }
                });
            }
        } else {
            questionArea.innerHTML = questionCardHtml;
        }

        // Attach next button listener
        const nextBtn = document.getElementById('next-btn');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                currentIndex++;
                if (currentIndex < totalQuestions) {
                    renderQuestion(currentIndex);
                } else {
                    completeQuiz();
                }
            });
        }

        // Attach admin manage button listener if admin
        const adminManageBtn = document.getElementById('btn-admin-manage');
        if (adminManageBtn) {
            adminManageBtn.addEventListener('click', () => {
                openAdminModal(q, index);
            });
        }

        // Attach click handlers to options
        document.querySelectorAll('.option-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!answered) {
                    submitAnswer(q.id, btn.dataset.option);
                }
            });
        });
    }

    /**
     * Submit an answer via AJAX
     */
    async function submitAnswer(questionId, selectedOption) {
        if (answered) return;
        answered = true;

        // Disable all buttons immediately
        document.querySelectorAll('.option-btn').forEach(btn => {
            btn.classList.add('disabled');
        });

        // Highlight selected option
        const selectedBtn = document.querySelector(`[data-option="${selectedOption}"]`);
        if (selectedBtn) {
            selectedBtn.classList.add('selected');
        }

        try {
            const response = await fetch(`${baseUrl}/api/submit_answer.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    session_id: sessionId,
                    question_id: questionId,
                    selected_option: selectedOption,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();

            if (response.ok) {
                // Mark correct answer
                const correctBtn = document.querySelector(`[data-option="${data.correct_option}"]`);
                if (correctBtn) {
                    correctBtn.classList.remove('disabled');
                    correctBtn.classList.add('correct');

                    const correctResult = document.createElement('span');
                    correctResult.className = 'option-result';
                    correctResult.textContent = '✓';
                    correctBtn.appendChild(correctResult);
                }

                if (data.correct) {
                    score++;
                } else if (selectedBtn) {
                    // Mark wrong answer
                    selectedBtn.classList.add('wrong');
                    const wrongResult = document.createElement('span');
                    wrongResult.className = 'option-result';
                    wrongResult.textContent = '✗';
                    selectedBtn.appendChild(wrongResult);
                }

                // Show explanation
                if (data.explanation) {
                    const explanationArea = document.getElementById('explanation-area');
                    if (explanationArea) {
                        explanationArea.innerHTML = `
                            <div class="explanation">
                                <strong>💡 Explanation:</strong> ${escapeHtml(data.explanation)}
                            </div>
                        `;
                    }
                }

                // Show next button
                const quizActions = document.getElementById('quiz-actions');
                if (quizActions) {
                    quizActions.style.display = 'flex';
                }
            } else {
                console.error('Submit error:', data.error);
                answered = false;
                document.querySelectorAll('.option-btn').forEach(btn => {
                    btn.classList.remove('disabled', 'selected');
                });
            }
        } catch (error) {
            console.error('Network error:', error);
            answered = false;
            document.querySelectorAll('.option-btn').forEach(btn => {
                btn.classList.remove('disabled', 'selected');
            });
        }
    }

    /**
     * Complete the quiz — notify server and redirect to results
     */
    async function completeQuiz() {
        // Show loading
        questionArea.innerHTML = `
            <div class="question-card text-center" style="padding: var(--space-xl);">
                <div class="spinner"></div>
                <p style="margin-top: var(--space-md); color: var(--text-secondary); font-weight: 500;">
                    Calculating your final results...
                </p>
            </div>
        `;

        try {
            const response = await fetch(`${baseUrl}/api/complete_quiz.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    session_id: sessionId,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();

            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.href = `${baseUrl}/result.php?session_id=${sessionId}`;
            }
        } catch (error) {
            console.error('Complete error:', error);
            window.location.href = `${baseUrl}/result.php?session_id=${sessionId}`;
        }
    }

    /**
     * Open Admin Question Management Modal (Edit or Swap)
     */
    function openAdminModal(q, index) {
        let modal = document.getElementById('admin-quiz-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'admin-quiz-modal';
            modal.className = 'admin-modal-overlay';
            document.body.appendChild(modal);
        }

        modal.innerHTML = `
            <div class="admin-modal-content">
                <div class="admin-modal-header">
                    <div>
                        <h3 class="admin-modal-title">Admin: Manage Question #${index + 1}</h3>
                        <p class="admin-modal-subtitle">Question ID: ${q.id} ${q.case_study_id ? ' &bull; Attached to Case Study' : ''}</p>
                    </div>
                    <button type="button" class="admin-modal-close" id="admin-modal-close-btn">&times;</button>
                </div>
                <div id="admin-modal-alert" style="display:none;" class="admin-modal-alert"></div>
                <form id="admin-question-form" class="admin-modal-form">
                    <div class="admin-form-group">
                        <label class="admin-label" for="admin-q-text">Question Text</label>
                        <textarea id="admin-q-text" class="admin-input-textarea" rows="3" required>${escapeHtml(q.question_text || '')}</textarea>
                    </div>
                    <div class="admin-options-grid">
                        <div class="admin-form-group">
                            <label class="admin-label" for="admin-opt-a">Option A</label>
                            <input type="text" id="admin-opt-a" class="admin-input" value="${escapeHtml(q.option_a || '')}" required>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-label" for="admin-opt-b">Option B</label>
                            <input type="text" id="admin-opt-b" class="admin-input" value="${escapeHtml(q.option_b || '')}" required>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-label" for="admin-opt-c">Option C</label>
                            <input type="text" id="admin-opt-c" class="admin-input" value="${escapeHtml(q.option_c || '')}" required>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-label" for="admin-opt-d">Option D</label>
                            <input type="text" id="admin-opt-d" class="admin-input" value="${escapeHtml(q.option_d || '')}" required>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <div class="admin-form-group" style="flex: 1;">
                            <label class="admin-label" for="admin-correct-opt">Correct Option</label>
                            <select id="admin-correct-opt" class="admin-select" required>
                                <option value="A" ${q.correct_option === 'A' ? 'selected' : ''}>A</option>
                                <option value="B" ${q.correct_option === 'B' ? 'selected' : ''}>B</option>
                                <option value="C" ${q.correct_option === 'C' ? 'selected' : ''}>C</option>
                                <option value="D" ${q.correct_option === 'D' ? 'selected' : ''}>D</option>
                            </select>
                        </div>
                        <div class="admin-form-group" style="flex: 2;">
                            <label class="admin-label" for="admin-explanation">Explanation</label>
                            <textarea id="admin-explanation" class="admin-input-textarea" rows="2" placeholder="Explanation shown after answering">${escapeHtml(q.explanation || '')}</textarea>
                        </div>
                    </div>
                    <div class="admin-modal-footer">
                        <button type="button" class="btn btn-secondary" id="admin-swap-btn" title="Swap with an unused question from this chapter/subject">
                            🔄 Swap Question
                        </button>
                        <div class="admin-modal-right-actions">
                            <button type="button" class="btn btn-secondary" id="admin-cancel-btn">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="admin-save-btn">💾 Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        `;

        modal.style.display = 'flex';

        const closeBtn = document.getElementById('admin-modal-close-btn');
        const cancelBtn = document.getElementById('admin-cancel-btn');
        const form = document.getElementById('admin-question-form');
        const swapBtn = document.getElementById('admin-swap-btn');
        const alertBox = document.getElementById('admin-modal-alert');

        function closeModal() {
            modal.style.display = 'none';
        }

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // Form submission - Save Changes
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const saveBtn = document.getElementById('admin-save-btn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const resp = await fetch(`${baseUrl}/api/admin_update_question.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        question_id: q.id,
                        question_text: document.getElementById('admin-q-text').value,
                        option_a: document.getElementById('admin-opt-a').value,
                        option_b: document.getElementById('admin-opt-b').value,
                        option_c: document.getElementById('admin-opt-c').value,
                        option_d: document.getElementById('admin-opt-d').value,
                        correct_option: document.getElementById('admin-correct-opt').value,
                        explanation: document.getElementById('admin-explanation').value,
                        csrf_token: csrfToken
                    })
                });

                const result = await resp.json();
                if (result.success && result.question) {
                    Object.assign(q, result.question);
                    questions[index] = q;
                    closeModal();
                    renderQuestion(index);
                } else {
                    alertBox.textContent = result.error || 'Failed to save question.';
                    alertBox.style.display = 'block';
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 Save Changes';
                }
            } catch (err) {
                console.error(err);
                alertBox.textContent = 'Network error occurred.';
                alertBox.style.display = 'block';
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Save Changes';
            }
        });

        // Swap Question handler
        swapBtn.addEventListener('click', async () => {
            if (!confirm('Are you sure you want to replace this question with another question from the database?')) {
                return;
            }

            swapBtn.disabled = true;
            swapBtn.textContent = 'Swapping...';

            try {
                const existingIds = questions.map(item => item.id);
                const resp = await fetch(`${baseUrl}/api/admin_swap_question.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        session_id: sessionId,
                        current_question_id: q.id,
                        chapter_id: chapterId,
                        subject_id: subjectId,
                        existing_ids: existingIds,
                        csrf_token: csrfToken
                    })
                });

                const result = await resp.json();
                if (result.success && result.new_question) {
                    questions[index] = result.new_question;
                    closeModal();
                    renderQuestion(index);
                } else {
                    alertBox.textContent = result.error || 'Failed to swap question.';
                    alertBox.style.display = 'block';
                    swapBtn.disabled = false;
                    swapBtn.textContent = '🔄 Swap Question';
                }
            } catch (err) {
                console.error(err);
                alertBox.textContent = 'Network error occurred.';
                alertBox.style.display = 'block';
                swapBtn.disabled = false;
                swapBtn.textContent = '🔄 Swap Question';
            }
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
