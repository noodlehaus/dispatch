<?php
include '../../src/dispatch.php';

before(function () {
  echo "before()<br>";
});

before(function () {
  echo "before() again<br>";
});

after(function () {
  echo "after()<br>";
});

after(function () {
  echo "after() again<br>";
});

filter('name', function ($name) {
  scope('greeting', "Hello there, {$name}!<br>");
});

filter('name', function ($name) {
  scope('farewell', "Bye bye, {$name}!<br>");
});

on('GET', '/greet/:name', function ($name) {
  echo scope('greeting');
  echo scope('farewell');
});

dispatch();
?>
