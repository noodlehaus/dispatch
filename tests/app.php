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

on('PUT', '/index', function () {
  echo "PUT route test";
});

on('DELETE', '/index/:id', function ($id) {
  echo "DELETE route test";
});

on('GET', '/json', function () {
  json_out([
    'name' => 'noodlehaus',
    'project' => 'dispatch'
  ]);
});

on('GET', '/jsonp', function () {
  json_out([
    'name' => 'noodlehaus',
    'project' => 'dispatch'
  ], 'callback');
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

on('GET', '/partial/:name', function ($name) {
  echo partial('partial', ['name' => $name]);
});

on('GET', '/template/:name', function ($name) {
  render('template', ['name' => $name]);
});

on('GET', '/session/setup', function () {
  session('name', 'i am dispatch');
  session('type', 'php framework');
});

on('GET', '/session/check', function () {
  session('type', null);
  if (session('type'))
    echo "type is still set";
  echo session('name');
});

dispatch();
?>
