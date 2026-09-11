/**
 * QuizMaster — General Utilities
 */

document.addEventListener('DOMContentLoaded', () => {

    // ---- Theme Toggle ----
    const themeToggle = document.getElementById('theme-toggle');
    const STORAGE_KEY = 'quizmaster-theme';

    function getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'dark';
    }

    function setTheme(theme) {
        if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        localStorage.setItem(STORAGE_KEY, theme);
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = getCurrentTheme();
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // ---- Mobile Nav Toggle ----
    const navToggle = document.getElementById('nav-toggle');
    const navMenu = document.getElementById('nav-menu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('active');
            }
        });
    }

    // ---- Auto-dismiss flash messages ----
    const flashContainer = document.getElementById('flash-container');
    if (flashContainer) {
        const flashes = flashContainer.querySelectorAll('.flash');
        flashes.forEach((flash, index) => {
            setTimeout(() => {
                flash.style.transition = 'all 0.4s ease';
                flash.style.opacity = '0';
                flash.style.transform = 'translateX(100%)';
                setTimeout(() => flash.remove(), 400);
            }, 4000 + (index * 500));
        });
    }

    // ---- Smooth card entrance animations ----
    const cards = document.querySelectorAll('.card, .chapter-card, .stat-card');
    if (cards.length > 0 && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 80);
                    observer.unobserve(entry.target);
                }
            });
        }, {threshold: 0.1});

        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            observer.observe(card);
        });
    }
});
