<? if (flash('secret-message')): ?>
  <h1><?= h(flash('secret-message')) ?></h1>
<? else: ?>
  <h1>Start at the <a href="/one">first page</a></h1>
<? endif; ?>
