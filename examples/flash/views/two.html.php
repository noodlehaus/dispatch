<? if (flash('secret-message')): ?>
  <?= h(flash('secret-message')) ?>
<? else: ?>
  Start at the <a href="/one">first page</a>
<? endif; ?>
