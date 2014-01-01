<? if (flash('secret-message')): ?>
  <?= html(flash('secret-message')) ?>
<? else: ?>
  Start at the <a href="/one">first page</a>
<? endif; ?>
