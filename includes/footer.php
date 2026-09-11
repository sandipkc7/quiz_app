    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    <?php if (isset($extra_js)): ?>
        <script src="<?= BASE_URL ?>/assets/js/<?= $extra_js ?>"></script>
    <?php endif; ?>
</body>
</html>
