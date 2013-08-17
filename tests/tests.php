<?php
include '../src/dispatch.php';
include './helpers.php';

define('URL', 'http://localhost:1234');

start_http('0.0.0.0', '1234', 'app.php');

/*-------------
 * local tests
 */

test('config setting and getting', function () {
  config('one', 1);
  config('false', false);
  assert(config('one') === 1);
  assert(config('false') === false);
  assert(config('invalid') === null);
});

test('config setting using an array', function () {
  config([
    'name' => 'noodlehaus',
    'project' => 'dispatch'
  ]);
  assert(config('name') === 'noodlehaus');
  assert(config('project') === 'dispatch');
});

test('site path setting and getting', function () {
  config('dispatch.url', 'http://localhost:8888/mysite/');
  assert(site() === 'http://localhost:8888/mysite/');
  assert(site(true) === '/mysite');
});

test('url encoding', function () {
  $s = 'name=noodlehaus&project=dispatch';
  assert(u($s) === urlencode($s));
});

test('html escaping', function () {
  assert(h('&') === '&amp;');
});

test('cross-scope values', function () {
  scope('one', 1);
  call_user_func(function () {
    assert(scope('one') === 1);
  });
});

/*--------------
 * remote tests
 */

test('before routines', function () {
  $res = curly('GET', URL.'/index');
  assert(preg_match('/BEFORE METHOD: GET/', $res));
  assert(preg_match('/BEFORE PATH: \/index/', $res));
});

test('after routines', function () {
  $res = curly('GET', URL.'/index');
  assert(preg_match('/AFTER METHOD: GET/', $res));
  assert(preg_match('/AFTER PATH: \/index/', $res));
});

test('error triggers', function () {
  $res = curly('GET', URL.'/error');
  assert(preg_match('/500 page error/i', $res));
});

test('custom error handler', function () {
  $res = curly('GET', URL.'/not-found');
  assert(preg_match('/file not found/', $res));
});

test('GET handler', function () {
  $res = curly('GET', URL.'/index?name=dispatch');
  assert(preg_match('/GET received dispatch and dispatch/', $res));
});

test('POST handler', function () {
  $res = curly('POST', URL.'/index', ['name' => 'dispatch']);
  assert(preg_match('/POST received dispatch and dispatch/i', $res));
});

test('PUT handler', function () {
  $res = curly('PUT', URL.'/index', ['name' => 'dispatch']);
  assert(preg_match('/PUT received dispatch/i', $res));
});

test('DELETE handler', function () {
  $res = curly('DELETE', URL.'/index/1');
  assert(preg_match('/DELETE route test/i', $res));
});

test('POST file upload', function () {
  $att = curl_file_create(__DIR__.'/upload.txt');
  $res = curly('POST', URL.'/upload', ['attachment' => $att]);
  assert(preg_match('/received upload\.txt/', $res));
});

test('json output', function () {
  $res = curly('GET', URL.'/json');
  $val = '{"name":"noodlehaus","project":"dispatch"}';
  assert(preg_match('/application\/json/', $res));
  assert(preg_match('/'.preg_quote($val).'/', $res));
});

test('jsonp output', function () {
  $res = curly('GET', URL.'/jsonp');
  $val = 'callback({"name":"noodlehaus","project":"dispatch"})';
  assert(preg_match('/application\/javascript/', $res));
  assert(preg_match('/'.preg_quote($val).'/', $res));
});

test('302 redirect (default)', function () {
  $res = curly('GET', URL.'/redirect/302');
  assert(preg_match('/302 found/i', $res));
  assert(preg_match('/Location: \/index/i', $res));
});

test('301 redirect', function () {
  $res = curly('GET', URL.'/redirect/301');
  assert(preg_match('/301 moved permanently/i', $res));
  assert(preg_match('/Location: \/index/i', $res));
});

test('route filter', function () {
  $res = curly('GET', URL.'/index/123');
  assert(preg_match('/id found/i', $res));
  assert(preg_match('/id = 123/i', $res));
});

test('cookie setting', function () {
  $res = curly('GET', URL.'/cookie-set');
  assert(preg_match('/set-cookie: cookie=/i', $res));
  $res = curly('GET', URL.'/cookie-get');
  assert(preg_match('/cookie=123/i', $res));
});

test('session setting', function () {
  curly('GET', URL.'/session/setup');
  $res = curly('GET', URL.'/session/check');
  assert(preg_match('/i am dispatch/i', $res));
  assert(!preg_match('/type is still set/i', $res));
});

test('params fetching', function () {
  $res = curly('GET', URL.'/params?one=1&two=2');
  assert(preg_match('/one=1/', $res));
  assert(preg_match('/two=2/', $res));
});

test('flash messages', function () {
  curly('GET', URL.'/flash-set');
  $res = curly('GET', URL.'/flash-get');
  assert(preg_match('/message=success/i', $res));
});

test('partials', function () {
  $res = curly('GET', URL.'/partial/dispatch');
  assert(preg_match('/dispatch is awesome/', $res));
});

test('template rendering', function () {
  $res = curly('GET', URL.'/template/dispatch');
  assert(preg_match('/<!doctype html>/i', $res));
  assert(preg_match('/dispatch is awesome/', $res));
});

test_summary();
?>
