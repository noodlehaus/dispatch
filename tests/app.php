<?php
include '../src/dispatch.php';

// settings
config('dispatch.flash_cookie', '_F');
config('dispatch.views', './views');
config('dispatch.layout', 'layout');
config('dispatch.url', 'http://localhost:1234/');

// before routine
before(function ($method, $path) {
  echo "BEFORE METHOD: {$method}, BEFORE PATH: {$path}".PHP_EOL;
});

// after routine
after(function ($method, $path) {
  echo "AFTER METHOD: {$method}, AFTER PATH: {$path}".PHP_EOL;
});

// routines for testing
error(404, function () {
  echo "file not found";
});

on('GET', '/error', function () {
  error(500);
});

on('GET', '/index', function () {
  $name1 = params('name');
  $name2 = $_GET['name'];
  echo "GET received {$name1} and {$name2}";
});

on('POST', '/index', function () {
  $name1 = params('name');
  $name2 = $_POST['name'];
  echo "POST received {$name1} and {$name2}";
});

on('PUT', '/index', function () {
  parse_str(request_body(), $vars);
  echo "PUT received {$vars['name']}";
});

on('PUT', '/override', function () {
  echo "PUT received via _method";
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

on('POST', '/upload', function () {
  $info = upload('attachment');
  if (is_array($info) && is_uploaded_file($info['tmp_name']))
    echo "received {$info['name']}";
  else
    echo "failed upload";
});

on('GET', '/download', function () {
  download('./README.md', 'readme.txt', 60*60*24*365);
});

prefix('books', function () {
  on('GET', '/list', function () {
    echo "book list";
  });
  prefix('chapters', function () {
    on('GET', '/list', function () {
      echo "chapter list";
    });
  });
});

on('GET', '/list', function () {
  echo "different list";
});

dispatch();
?>
