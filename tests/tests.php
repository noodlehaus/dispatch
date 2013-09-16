<?php
include '../src/dispatch.php';
include './helpers.php';

define('URL', 'http://localhost:1234');

start_http('0.0.0.0', '1234', 'app.php');

/*-------------
 * local tests
 */

test('config()', function () {
  config('one', 1);
  config('false', false);
  assert(config('one') === 1);
  assert(config('false') === false);
  assert(config('invalid') === null);
  config([
    'name' => 'noodlehaus',
    'project' => 'dispatch'
  ]);
  assert(config('name') === 'noodlehaus');
  assert(config('project') === 'dispatch');
});

test('site()', function () {
  config('dispatch.url', 'http://localhost:8888/mysite/');
  assert(site() === 'http://localhost:8888/mysite/');
  assert(site(true) === '/mysite');
});

test('url()', function () {
  $s = 'name=noodlehaus&project=dispatch';
  assert(url($s) === urlencode($s));
});

test('html()', function () {
  assert(html('&') === '&amp;');
});

test('scope()', function () {
  scope('one', 1);
  call_user_func(function () {
    assert(scope('one') === 1);
  });
});

/*--------------
 * remote tests
 */

test('before()', function () {
  $res = curly('GET', URL.'/index?name=dispatch');
  assert(preg_match('/BEFORE METHOD: GET/', $res));
  assert(preg_match('/BEFORE PATH: index/', $res));
});

test('after()', function () {
  $res = curly('GET', URL.'/index?name=dispatch');
  assert(preg_match('/AFTER METHOD: GET/', $res));
  assert(preg_match('/AFTER PATH: index/', $res));
});

test('error(code)', function () {
  $res = curly('GET', URL.'/error');
  assert(preg_match('/500 page error/i', $res));
});

test('error(code, callable)', function () {
  $res = curly('GET', URL.'/not-found');
  assert(preg_match('/file not found/', $res));
});

test('on(GET)', function () {
  $res = curly('GET', URL.'/index?name=dispatch');
  assert(preg_match('/GET received dispatch and dispatch/', $res));
});

test('on(POST)', function () {
  $res = curly('POST', URL.'/index', ['name' => 'dispatch']);
  assert(preg_match('/POST received dispatch and dispatch/i', $res));
});

test('on(PUT)', function () {
  $res = curly('PUT', URL.'/index', ['name' => 'dispatch']);
  assert(preg_match('/PUT received dispatch/i', $res));
});

test('on(DELETE)', function () {
  $res = curly('DELETE', URL.'/index/1');
  assert(preg_match('/DELETE route test/i', $res));
});

test('upload()', function () {

  if (PHP_VERSION_ID < 50500)
    $att = '@'.__DIR__.'/upload.txt';
  else
    $att = curl_file_create(__DIR__.'/upload.txt');

  $res = curly('POST', URL.'/upload', ['attachment' => $att]);
  assert(preg_match('/received upload\.txt/', $res));
});

test('download()', function () {
  $res = curly('GET', URL.'/download');
  assert(preg_match('/filename=readme\.txt/', $res));
  assert(preg_match('/maxage=31536000/', $res));
  assert(preg_match('/ETag: '.md5('./README.md').'/', $res));
});

test('method override (_method, X-HTTP-Method-Override)', function () {
  $res = curly('POST', URL.'/override', ['_method' => 'PUT']);
  assert(preg_match('/PUT received via _method/i', $res));
  $res = curly(
    'POST',
    URL.'/override',
    ['data' => 'nothing'],
    [CURLOPT_HTTPHEADER => ['X-HTTP-Method-Override: PUT']]
  );
  assert(preg_match('/PUT received via _method/i', $res));
});

test('json_out()', function () {
  $res = curly('GET', URL.'/json');
  $val = '{"name":"noodlehaus","project":"dispatch"}';
  assert(preg_match('/application\/json/', $res));
  assert(preg_match('/'.preg_quote($val).'/', $res));
  $res = curly('GET', URL.'/jsonp');
  $val = 'callback({"name":"noodlehaus","project":"dispatch"})';
  assert(preg_match('/application\/javascript/', $res));
  assert(preg_match('/'.preg_quote($val).'/', $res));
});

test('redirect()', function () {
  $res = curly('GET', URL.'/redirect/302');
  assert(preg_match('/302 found/i', $res));
  assert(preg_match('/Location: \/index/i', $res));
  $res = curly('GET', URL.'/redirect/301');
  assert(preg_match('/301 moved permanently/i', $res));
  assert(preg_match('/Location: \/index/i', $res));
});

test('filter()', function () {
  $res = curly('GET', URL.'/index/123');
  assert(preg_match('/id found/i', $res));
  assert(preg_match('/id = 123/i', $res));
});

test('cookie()', function () {
  $res = curly('GET', URL.'/cookie-set');
  assert(preg_match('/set-cookie: cookie=/i', $res));
  $res = curly('GET', URL.'/cookie-get');
  assert(preg_match('/cookie=123/i', $res));
});

test('session()', function () {
  curly('GET', URL.'/session/setup');
  $res = curly('GET', URL.'/session/check');
  assert(preg_match('/i am dispatch/i', $res));
  assert(!preg_match('/type is still set/i', $res));
});

test('params()', function () {
  $res = curly('GET', URL.'/params?one=1&two=2');
  assert(preg_match('/one=1/', $res));
  assert(preg_match('/two=2/', $res));
});

test('flash()', function () {
  curly('GET', URL.'/flash-set');
  $res = curly('GET', URL.'/flash-get');
  assert(preg_match('/message=success/i', $res));
});

test('partial()', function () {
  $res = curly('GET', URL.'/partial/dispatch');
  assert(preg_match('/dispatch is awesome/', $res));
});

test('render()', function () {
  $res = curly('GET', URL.'/template/dispatch');
  assert(preg_match('/<!doctype html>/i', $res));
  assert(preg_match('/dispatch is awesome/', $res));
});

test('prefix()', function () {
  $res1 = curly('GET', URL.'/books/list');
  $res2 = curly('GET', URL.'/books/chapters/list');
  $res3 = curly('GET', URL.'/list');
  assert(preg_match('/book list/', $res1));
  assert(preg_match('/chapter list/', $res2));
  assert(preg_match('/different list/', $res3));
});

test_summary();
?>
