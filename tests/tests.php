<?php
include './helpers.php';

define('URL', 'http://localhost:1234');

start_http('0.0.0.0', '1234', 'app.php');

test('error triggers', function () {
  $res = do_get(URL.'/error');
  assert(preg_match('/500 page error/i', $res));
});

test('custom error handler', function () {
  $res = do_get(URL.'/not-found');
  assert(preg_match('/file not found/', $res));
});

test('GET handler', function () {
  $res = do_get(URL.'/index');
  assert(preg_match('/GET route test/', $res));
});

test('POST handler', function () {
  $res = do_post(URL.'/index');
  assert(preg_match('/POST route test/i', $res));
});

test('302 redirect (default)', function () {
  $res = do_get(URL.'/redirect/302');
  assert(preg_match('/302 found/i', $res));
  assert(preg_match('/Location: \/index/i', $res));
});

test('301 redirect', function () {
  $res = do_get(URL.'/redirect/301');
  assert(preg_match('/301 moved permanently/i', $res));
  assert(preg_match('/Location: \/index/i', $res));
});

test('route filter', function () {
  $res = do_get(URL.'/index/123');
  assert(preg_match('/id found/i', $res));
  assert(preg_match('/id = 123/i', $res));
});

test('cookie setting', function () {
  $res = do_get(URL.'/cookie-set');
  assert(preg_match('/set-cookie: cookie=/i', $res));
  $res = do_get(URL.'/cookie-get');
  assert(preg_match('/cookie=123/i', $res));
});

test('params fetching', function () {
  $res = do_get(URL.'/params?one=1&two=2');
  assert(preg_match('/one=1/', $res));
  assert(preg_match('/two=2/', $res));
});

test('flash messages', function () {
  do_get(URL.'/flash-set');
  $res = do_get(URL.'/flash-get');
  assert(preg_match('/message=success/i', $res));
});
?>
