<?php // footer.php — close page layout ?>

</div><!-- .page-wrap -->

<div id="toast"></div>

<script>
// ── Toast utility ─────────────────────────────────
function showToast(msg, isError = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'show' + (isError ? ' error' : '');
  clearTimeout(t._t);
  t._t = setTimeout(() => { t.className = ''; }, 4000);
}
<?php if (!empty($_SESSION['toast'])): ?>
showToast(<?= json_encode($_SESSION['toast']['msg']) ?>, <?= json_encode($_SESSION['toast']['error'] ?? false) ?>);
<?php unset($_SESSION['toast']); endif; ?>
</script>

<!-- Three.js animated background -->
<script src="js/background.js"></script>
</body>
</html>
