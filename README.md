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

---

## 🛠️ Tech Stack

- **Backend**: PHP 8 (Pure PHP, zero heavy framework dependencies)
- **Database**: MySQL with PDO (`utf8mb4_unicode_ci`)
- **Frontend**: Vanilla HTML5, Vanilla CSS3 (Custom Glassmorphism design system), Vanilla JavaScript (AJAX / Fetch API)
