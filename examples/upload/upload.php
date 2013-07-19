<?php
include '../../src/dispatch.php';

config('dispatch.views', './views');
config('dispatch.layout', 'layout');

on(['GET', 'POST'], '/single', function () {
  $file_info = upload('file');
  render('single', ['file' => $file_info]);
});

on(['GET', 'POST'], '/multi', function () {
  $file_info = upload('file');
  render('multi', ['file' => $file_info]);
});

dispatch();
?>
