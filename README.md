# ⚡ QuizMaster — Multi-Language Quiz Platform

A modern, responsive, multi-language quiz application built with **PHP 8+**, **MySQL**, and **Vanilla HTML/CSS/JavaScript**. Features a dark glassmorphism aesthetic with light mode toggle, real-time feedback, unlimited timer, chapter selection, AI question import, and full UTF-8/Unicode support (e.g. Devanagari/Nepali).

---

## ✨ Features

- 🔐 **User Authentication**: Secure register/login with bcrypt password hashing and CSRF protection.
- 🎨 **Dark / Light Theme Switcher**: Modern glassmorphism UI with smooth toggle and `localStorage` persistence.
- 📚 **Subjects & Chapters**: Browse subjects (Science, Math, General Knowledge, Computer Science) and select chapters with question counts.
- 🎯 **Interactive 20-Question Quiz**:
  - Compact desktop layout with 2x2 grid (zero scrolling required).
  - Instant answer feedback (correct in green, incorrect in red with shake/pulse animations).
  - Immediate explanation display after each question.
  - Keyboard shortcuts (`1`-`4` or `A`-`D` to select, `Enter`/`Space` to advance).
  - Subject-level practice mode ("Start 20-Question Subject Quiz").
  - Unlimited timer & no negative scoring.
- 📊 **Quiz History & Stats**: Dedicated performance tracking page with accuracy percentages, total attempts, and past answer reviews.
- ⚙️ **Admin Panel**:
  - Manage subjects, chapters, and questions.
  - 🤖 **Bulk AI & CSV Import**: Import questions generated from ChatGPT, Claude, or Gemini in standard JSON/CSV format.
- 🌐 **Full UTF-8 / Multi-Language Support**: Complete `utf8mb4` support for Devanagari, Nepali, and other non-Latin scripts.

---

## 🚀 Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8+)
- Web browser

### Installation

1. **Clone or copy the project into your XAMPP web root**:
   ```bash
   git clone https://github.com/sandipkc7/quiz_app.git c:/xampp/htdocs/quiz
   ```

2. **Configure Environment Variables**:
   Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
   Adjust database credentials if different from default XAMPP settings (`root` with no password).

3. **Run Database Migrations (Automated)**:
   Run the CLI migration runner:
   ```bash
   php migrate.php
   ```
   To populate starter demo subjects, chapters, and the default admin account:
   ```bash
   php migrate.php --seed
   ```
   To inspect applied and pending migrations:
   ```bash
   php migrate.php --status
   ```

4. **Launch Application**:
   Navigate to your local or production URL:
   ```
   http://localhost/quiz/
   ```

### Default Credentials

- **Admin Account**:
  - Username: `admin`
  - Password: `admin123`
- Or register a new user directly from the registration page.

---

## 🤖 AI Prompting Template for Questions

To generate questions using AI (ChatGPT, Claude, Gemini) for bulk import into the admin panel, use the following schema:

```json
[
  {
    "question": "What is the capital of Nepal?",
    "option_a": "Pokhara",
    "option_b": "Kathmandu",
    "option_c": "Lalitpur",
    "option_d": "Biratnagar",
    "correct_option": "B",
    "explanation": "Kathmandu is the capital and largest city of Nepal."
  }
]
```

## 🧠 Smart Question Rotation & Chapter Progression

QuizMaster features an intelligent, user-aware progression engine (`getQuizQuestionsForUser()`) that prevents repetitive questions and maximizes learning efficiency across multiple chapter attempts.

### 3-Tier Progression Hierarchy (Automated):

| Tier | Priority Category | Description & Behavior |
| :--- | :--- | :--- |
| **Tier 1** | 🆕 **Unattempted / New Questions** | Automatically queries fresh, unattempted questions in sequence. For example, in a chapter with 80 questions: <br>• **Attempt 1**: Questions 1–20 <br>• **Attempt 2**: Questions 21–40 <br>• **Attempt 3**: Questions 41–60 <br>• **Attempt 4**: Questions 61–80 |
| **Tier 2** | 🎯 **Incorrect Questions (Weak Areas)** | Once all chapter questions have been attempted, the engine automatically prioritizes questions previously answered incorrectly to reinforce weak areas. |
| **Tier 3** | 🔄 **Least-Recently Practiced** | Cycles through older questions based on the time elapsed since they were last answered, enabling continuous circular practice with zero dead ends. |

> **Case Study Integrity**: When any question in a selection belongs to a Case Study or Passage, all sibling questions for that Case Study are automatically loaded together contiguously.

### ↺ Progress Reset & Clean Slate (Restart from Tier 1)
Users can reset their progress at any time to clear past accuracy records and restart question rotation from **Tier 1 (Question 1)**:
- **Per-Chapter Reset**: From [Chapters](file:///c:/xampp/htdocs/quiz/chapters.php) or after completing a quiz in [Results](file:///c:/xampp/htdocs/quiz/result.php) ("*Reset & Restart (Tier 1)*").
- **Subject-Level Reset**: Reset progress across all chapters in a subject from the [Chapters](file:///c:/xampp/htdocs/quiz/chapters.php) page or [History](file:///c:/xampp/htdocs/quiz/history.php) filter.
- **Global Account Reset**: Wipe all past attempts, scores, and statistics from [History](file:///c:/xampp/htdocs/quiz/history.php) or [Account Settings](file:///c:/xampp/htdocs/quiz/profile.php).

---

## 📖 Case Study & Multiline Question Features

- **Mobile Full-Screen Modal**: On mobile screens ($\le 860\text{px}$), Case Studies can be opened in a dedicated full-screen modal with large typography, safe scrolling, and a sticky "Back to Question" button.
- **Multiline & Formatted Questions**: Supports complex question formats (e.g. matching pairs `i.`, `ii.`, `iii.`, legal scenario facts, and tables) with line breaks preserved cleanly without collapsing.

---

## 🛠️ Tech Stack

- **Backend**: PHP 8 (Pure PHP, zero heavy framework dependencies)
- **Database**: MySQL with PDO (`utf8mb4_unicode_ci`)
- **Frontend**: Vanilla HTML5, Vanilla CSS3 (Custom Glassmorphism design system), Vanilla JavaScript (AJAX / Fetch API)

