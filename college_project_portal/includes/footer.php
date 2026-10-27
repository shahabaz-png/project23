<?php /** footer.php - closes the layout opened in header.php */ ?>
<?php if (current_user()): ?>
  </main>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
