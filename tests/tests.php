<?php
include __DIR__.'/../src/dispatch.php';
include __DIR__.'/helpers.php';

define('URL', 'http://localhost:1234');

start_http('0.0.0.0', '1234', __DIR__.'/app.php');

/*-------------
 * local tests
 */

test('config()', function () {
  config('one', 1);
  config('false', false);
  assert(config('one') === 1);
  assert(config('false') === false);
  assert(config('invalid') === null);
});

test('config() - array of keys', function () {
  config(array(
    'name' => 'noodlehaus',
    'project' => 'dispatch'
  ));
  assert(config('one') === 1);
  assert(config('name') === 'noodlehaus');
  assert(config('project') === 'dispatch');
});

test('config() - reset', function () {
  config();
  assert(config('name') === null);
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
  $res = curl('GET', URL.'/index?name=dispatch');
  assert(preg_match('/BEFORE METHOD: GET/', $res));
  assert(preg_match('/BEFORE PATH: index/', $res));
});

test('before() - with regex', function () {
  $res = curl('GET', URL.'/admin/before');
  assert(preg_match('/BEFORE via ADMIN/', $res));
});

test('after()', function () {
  $res = curl('GET', URL.'/index?name=dispatch');
  assert(preg_match('/AFTER METHOD: GET/', $res));
  assert(preg_match('/AFTER PATH: index/', $res));
});

test('after() - with regex', function () {
  $res = curl('GET', URL.'/admin/after');
  assert(preg_match('/AFTER via ADMIN/', $res));
});

test('error() - direct trigger', function () {
  $res = curl('GET', URL.'/error');
  assert(preg_match('/500 page error/i', $res));
});

test('error() - custom callback', function () {
  $res = curl('GET', URL.'/not-found');
  assert(preg_match('/file not found/', $res));
});

test('on() - GET', function () {
  $res = curl('GET', URL.'/index?name=one%20two');
  assert(preg_match('/GET received one two and one two/', $res));
});

test('on() - POST', function () {
  $res = curl('POST', URL.'/index', array('name' => 'dispatch'));
  assert(preg_match('/POST received dispatch and dispatch/i', $res));
});

test('on() - PUT', function () {
  $res = curl('PUT', URL.'/index', array('name' => 'dispatch'));
  assert(preg_match('/PUT received dispatch/i', $res));
});

test('on() - DELETE', function () {
  $res = curl('DELETE', URL.'/index/1');
  assert(preg_match('/DELETE route test/i', $res));
});

test('on() - any method', function () {
  $res = curl('GET', URL.'/any');
  assert(preg_match('/any method route test/i', $res));
});

test('files()', function () {
  if (PHP_VERSION_ID < 50500)
    $att = '@'.__DIR__.'/upload.txt';
  else
    $att = curl_file_create(__DIR__.'/upload.txt');
  $res = curl('POST', URL.'/upload', array('attachment' => $att));
  assert(preg_match('/received upload\.txt/', $res));
});

test('send()', function () {
  $res = curl('GET', URL.'/download');
  assert(preg_match('/filename=readme\.txt/', $res));
  assert(preg_match('/maxage=31536000/', $res));
  assert(preg_match('/ETag: '.md5('./README.md').'/', $res));
});

test('method override - via _method', function () {
  $res = curl('POST', URL.'/override', array('_method' => 'PUT'));
  assert(preg_match('/PUT received via _method/i', $res));
});

test('method override - via X-HTTP-Method-Override', function () {
  $res = curl(
    'POST',
    URL.'/override',
    array('data' => 'nothing'),
    array(CURLOPT_HTTPHEADER => array('X-HTTP-Method-Override: PUT'))
  );
  assert(preg_match('/PUT received via _method/i', $res));
});

test('json() - JSON response', function () {
  $res = curl('GET', URL.'/json');
  $val = '{"name":"noodlehaus","project":"dispatch"}';
  assert(preg_match('/application\/json/', $res));
  assert(preg_match('/'.preg_quote($val).'/', $res));
});

test('json() - JSON-P response', function () {
  $res = curl('GET', URL.'/jsonp');
  $val = 'callback({"name":"noodlehaus","project":"dispatch"})';
  assert(preg_match('/application\/javascript/', $res));
  assert(preg_match('/'.preg_quote($val).'/', $res));
});

test('is_xhr(), true', function () {
  $res = curl('GET', URL.'/ajax', array(), array(
    CURLOPT_HTTPHEADER => array('X-Requested-With: XMLHttpRequest'),
  ));
  assert(preg_match('/application\/json/', $res));
});

test('is_xhr(), false', function () {
  $res = curl('GET', URL.'/ajax');
  assert(preg_match('/text\/html/', $res));
});

test('redirect()', function () {
  $res = curl('GET', URL.'/redirect/302');
  assert(preg_match('/302 found/i', $res));
  assert(preg_match('/Location: \/index/i', $res));
});

test('filter() - symbol', function () {
  $res = curl('GET', URL.'/index/123');
  assert(preg_match('/id = 123/i', $res));
});

test('bind()', function () {
  $res = curl('GET', URL.'/md5/hello');
  assert(preg_match('/5d41402abc4b2a76b9719d911017c592-hello/', $res));
});

test('bind() - with cache', function () {
  $res = curl('GET', URL.'/authors/tolkien/books/lotr');
  assert(preg_match('/LOTR by TOLKIEN/', $res));
});

test('cookie() - set', function () {
  $res = curl('GET', URL.'/cookie-set');
  assert(preg_match('/set-cookie: cookie=/i', $res));
});

test('cookie() - get', function () {
  $res = curl('GET', URL.'/cookie-get');
  assert(preg_match('/cookie=123/i', $res));
});

test('session()', function () {
  curl('GET', URL.'/session/setup');
  $res = curl('GET', URL.'/session/check');
  assert(preg_match('/i am dispatch/i', $res));
  assert(!preg_match('/type is still set/i', $res));
});

test('request_headers()', function () {
  $res = curl(
    'POST',
    URL.'/request-headers',
    '{"name":"jaydee"}',
    array(CURLOPT_HTTPHEADER => array('Content-type: application/json'))
  );
  assert(preg_match('/application\/json/', $res));
});

test('request_body()', function () {
  $res = curl(
    'POST',
    URL.'/request-body',
    '{"name":"jaydee"}',
    array(CURLOPT_HTTPHEADER => array('Content-type: application/json'))
  );
  assert(preg_match('/name=jaydee/', $res));
});

test('request_body() - temp file', function () {
  $res = curl(
    'POST',
    URL.'/request-body-file',
    '{"name":"jaydee"}',
    array(CURLOPT_HTTPHEADER => array('Content-type: application/json'))
  );
  assert(preg_match('/name=jaydee/', $res));
});

test('params()', function () {
  $res = curl('GET', URL.'/params?one=1&two=2');
  assert(preg_match('/one=1/', $res));
  assert(preg_match('/two=2/', $res));
});

test('flash() - set', function () {
  curl('GET', URL.'/flash-set');
  $res = curl('GET', URL.'/flash-get');
  assert(preg_match('/message=success/i', $res));
});

test('flash() - get', function () {
  $res = curl('GET', URL.'/flash-get');
  assert(!preg_match('/message=success/i', $res));
});

test('flash() - now messages', function () {
  curl('GET', URL.'/flash-set');
  $res = curl('GET', URL.'/flash-get');
  assert(preg_match('/flash-now is null/i', $res));
});

test('partial()', function () {
  $res = curl('GET', URL.'/partial/dispatch');
  assert(preg_match('/dispatch is awesome/', $res));
});

test('render()', function () {
  $res = curl('GET', URL.'/template/dispatch');
  assert(preg_match('/<!doctype html>/i', $res));
  assert(preg_match('/dispatch is awesome/', $res));
});

test('inline()', function () {
  $res = curl('GET', URL.'/inline');
  assert(preg_match('/inline content/i', $res));
});

test('inline() - with locals', function () {
  $res = curl('GET', URL.'/inline/locals');
  assert(preg_match('/name=dispatch/i', $res));
});

test('inline() - with callback', function () {
  $res = curl('GET', URL.'/inline/callback');
  assert(preg_match('/name=dispatch/i', $res));
});

test('prefix()', function () {
  $res1 = curl('GET', URL.'/books/list');
  $res2 = curl('GET', URL.'/books/chapters/list');
  $res3 = curl('GET', URL.'/list');
  assert(preg_match('/book list/', $res1));
  assert(preg_match('/chapter list/', $res2));
  assert(preg_match('/different list/', $res3));
});

test_summary();
?>
