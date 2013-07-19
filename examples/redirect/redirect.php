<?php
include '../../src/dispatch.php';

error(404, function () {
  redirect('/index', 301);
});

on('GET', '/index', function () {
  echo "You're stuck!";
});

dispatch();
?>
