<?php
include '../../src/dispatch.php';

before(function () {
  echo "before()<br>";
});

after(function () {
  echo "after()<br>";
});

filter('name', function ($name) {
  scope('greeting', "Hello there, {$name}!");
});

on('GET', '/greet/:name', function ($name) {
  echo scope('greeting')."<br>";
});

dispatch();
?>
