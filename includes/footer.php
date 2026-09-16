    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= file_exists(__DIR__ . '/../assets/js/app.js') ? filemtime(__DIR__ . '/../assets/js/app.js') : '1.1' ?>"></script>
    <?php if (isset($extra_js)): ?>
        <script src="<?= BASE_URL ?>/assets/js/<?= $extra_js ?>?v=<?= file_exists(__DIR__ . '/../assets/js/' . $extra_js) ? filemtime(__DIR__ . '/../assets/js/' . $extra_js) : '1.1' ?>"></script>
    <?php endif; ?>
</body>
</html>
