<?php
include '../src/dispatch.php';

// settings
config('dispatch.flash_cookie', '_F');
config('dispatch.views', './views');
config('dispatch.layout', 'layout');
config('dispatch.url', 'http://localhost:1234/');

// routines for testing
error(404, function () {
  echo "file not found";
});

on('GET', '/error', function () {
  error(500);
});

on('GET', '/index', function () {
  echo "GET route test";
});

on('POST', '/index', function () {
  echo "POST route test";
});

on('GET', '/redirect/302', function () {
  redirect('/index');
});

on('GET', '/redirect/301', function () {
  redirect('/index', 301);
});

filter('id', function () {
  echo "id found";
});

on('GET', '/index/:id', function ($id) {
  echo "id = {$id}";
});

on('GET', '/cookie-set', function () {
  cookie('cookie', '123');
  echo "cookie set";
});

on('GET', '/cookie-get', function () {
  $value = cookie('cookie');
  echo "cookie={$value}";
});

on('GET', '/params', function () {
  $one = params('one');
  $two = params('two');
  echo "one={$one}".PHP_EOL;
  echo "two={$two}".PHP_EOL;
});

on('GET', '/flash-set', function () {
  flash('message', 'success');
});

on('GET', '/flash-get', function () {
  echo 'message='.flash('message');
});

dispatch();
?>
