<?php
include '../../dispatch.php';

on('GET', '/greet/:name', function () {
  echo "Hello there, ".params('name');
});

dispatch();
?>
