<?php
include __DIR__.'/../src/dispatch.php';

// settings
config('dispatch.flash_cookie', '_F');
config('dispatch.views', __DIR__.'/views');
config('dispatch.layout', 'layout');
config('dispatch.url', 'http://localhost:1234/');

// before routine
before(function ($method, $path) {
  echo "BEFORE METHOD: {$method}, BEFORE PATH: {$path}".PHP_EOL;
});

// regex before routine
before('/^admin\//', function ($method, $path) {
  echo "BEFORE via ADMIN";
});

// after routine
after(function ($method, $path) {
  echo "AFTER METHOD: {$method}, AFTER PATH: {$path}".PHP_EOL;
});

// regex after routine
after('/^admin\//', function ($method, $path) {
  echo "AFTER via ADMIN";
});

// routines for testing
error(404, function () {
  echo "file not found";
});

on('GET', '/error', function () {
  error(500);
});

on('*', '/any', function () {
  echo "any method route test";
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
  $vars = request_body();
  echo "PUT received {$vars['name']}";
});

on('PUT', '/override', function () {
  echo "PUT received via _method";
});

on('DELETE', '/index/:id', function ($id) {
  echo "DELETE route test";
});

on('GET', '/json', function () {
  json(array(
    'name' => 'noodlehaus',
    'project' => 'dispatch'
  ));
});

on('GET', '/jsonp', function () {
  json(array(
    'name' => 'noodlehaus',
    'project' => 'dispatch'
  ), 'callback');
});

on('GET', '/redirect/:code', function ($code) {
  redirect('/index', (int) $code);
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

on('POST', '/request-headers', function () {
  echo request_headers('content-type');
});

on('POST', '/request-body', function () {
  $body = request_body();
  echo "name={$body['name']}";
});

on('POST', '/request-body-file', function () {
  $path = request_body($load = false);
  $body = json_decode(file_get_contents($path), true);
  echo "name={$body['name']}";
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
  flash('now', time(), true);
});

on('GET', '/flash-get', function () {
  echo 'message='.flash('message');
  if (!flash('now'))
    echo 'flash-now is null';
  else
    echo 'flash-now exists';
});

on('GET', '/partial/:name', function ($name) {
  echo partial('partial', array('name' => $name));
});

on('GET', '/template/:name', function ($name) {
  render('template', array('name' => $name));
});

on('GET', '/inline', inline('inline'));
on('GET', '/inline/locals', inline(
  'inline-locals',
  array('name' => 'dispatch')
));
on('GET', '/inline/callback', inline('inline-locals', function () {
  return array('name' => 'dispatch');
}));

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
  $info = files('attachment');
  if (is_array($info) && is_uploaded_file($info['tmp_name']))
    echo "received {$info['name']}";
  else
    echo "failed upload";
});

on('GET', '/download', function () {
  send('./README.md', 'readme.txt', 60*60*24*365);
});

bind('hashable', function ($hashable) {
  return md5($hashable);
});

on('GET', '/md5/:hashable', function ($hash) {
  echo $hash . '-' . params('hashable');
});

bind('author', function ($name) {
  return strtoupper($name);
});

bind('title', function ($title) {
  return sprintf('%s by %s', strtoupper($title), bind('author'));
});

on('GET', '/authors/:author/books/:title', function ($author, $title) {
  echo $title;
});

on('GET', '/list', function () {
  echo "different list";
});

on('GET', '/ajax', function () {
  if(is_xhr()) {
    json(array('ajax' => true));
  } else {
    echo "Not AJAX";
  }
});

on('GET', '/admin/:stub', function ($stub) {
  echo "{$stub}\n";
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

dispatch();
?>
