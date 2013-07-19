<?php
include '../../src/dispatch.php';

config('dispatch.views', './views');
config('dispatch.layout', 'layout');
config('dispatch.flash_cookie', '_F');

on('GET', '/one', function () {
  flash('secret-message', 'Nifty, right?');
  render('one');
});

on('GET', '/two', function () {
  render('two');
});

dispatch();
?>
