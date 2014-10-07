<?php
require __DIR__.'/../dispatch.php';

# settings tests
{
  # load 3 files - ini, callable php, array php
  settings('@'.__DIR__.'/fixtures/settings-example.ini');
  settings('@'.__DIR__.'/fixtures/settings-array.php');
  settings('@'.__DIR__.'/fixtures/settings-callable.php');

  # loose equality, since ini parsing returns strings for nums
  assert(settings('settings.one') == 1);
  assert(settings('settings.two') == 2);
  assert(settings('settings.three') == 3);
  assert(settings('settings.invalid') == null);

  # unsupported type
  try {
    settings('@settings.conf');
  } catch (Exception $e) {
    assert($e instanceof InvalidArgumentException);
  }

  # invalid data
  try {
    settings('@'.__DIR__.'/fixtures/settings-invalid.php');
  } catch (Exception $e) {
    assert($e instanceof InvalidArgumentException);
  }
}

# utility functions
{
  # ent() and url()
  assert(ent('john & marsha') === 'john &amp; marsha');
  assert(url('=') === '%3D');
  assert('<h1>dispatch</h1>' == trim(phtml(
    __DIR__.'/fixtures/template.phtml',
    ['name' => 'dispatch']
  )));
  assert(['name' => '', 'email' => ''] === blanks('name', 'email'));

  # ip() - least priority first
  $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
  assert(ip() === $_SERVER['REMOTE_ADDR']);
  $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.2';
  assert(ip() === $_SERVER['HTTP_X_FORWARDED_FOR']);
  $_SERVER['HTTP_CLIENT_IP'] = '127.0.0.3';
  assert(ip() === $_SERVER['HTTP_CLIENT_IP']);

  # stash tests
  stash('name', 'dispatch');
  assert(stash('name') === 'dispatch');
  stash('name', null);
  assert(stash('name') === null);
  stash('name', 'dispatch');
  stash();
  assert(stash('name') === null);
}

# header functions
{
  # invalid call
  try {
    headers();
  } catch (Exception $e) {
    assert($e instanceof BadFunctionCallException);
  }

  # fake request headers
  $_SERVER['CONTENT_LENGTH'] = 1024;
  $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
  $_SERVER['HTTP_X_AUTH_TOKEN'] = 'some-token';

  assert(headers('content-length') === 1024);
  assert(headers('content-type') === 'application/x-www-form-urlencoded');
  assert(headers('x-auth-token') === 'some-token');

  if (function_exists('xdebug_get_headers')) {

    # test header setting
    headers('x-powered-by', 'dispatch', true);
    headers('x-authored-by', 'noodlehaus', true);
    assert(headers_get_clean() === [
      'x-powered-by: dispatch',
      'x-authored-by: noodlehaus'
    ]);

    # cookie fetch
    $_COOKIE = ['name' => 'dispatch'];
    assert(cookies('name') === 'dispatch');
    assert(cookies('invalid') === null);

    # cookie set
    cookies('title', 'john & marsha', time() + 3600, '/');
    assert(strpos(
      headers_get_clean()[0],
      'Set-Cookie: title=john+%26+marsha; expires='
    ) === 0);

    # json response
    ob_start();
    json(['name' => 'dispatch']);
    assert(ob_get_clean() === json_encode(['name' => 'dispatch']));
    assert(strpos(
      headers_get_clean()[0],
      'Content-type: application/json'
    ) === 0);

    # no-cache headers
    ob_start();
    nocache('dispatch');
    ob_get_clean();
    $list = headers_get_clean();
    assert($list[0] === 'Expires: Tue, 13 Mar 1979 18:00:00 GMT');
    assert(strpos($list[1], 'Last-Modified: ') === 0);
    assert($list[2] === 'Cache-Control: no-store, no-cache, must-revalidate');
    assert($list[3] === 'Cache-Control: post-check=0, pre-check=0');
    assert($list[4] === 'Pragma: no-cache');

    # status
    status(201);
    assert(http_response_code() === 201);

    # redirect (we can't test halting flag)
    redirect('/redir1', 302);
    assert(http_response_code() === 302);
    assert(headers_get_clean()[0] === 'Location: /redir1');

  } else {
    echo "Xdebug module is not available. Header tests skipped.\n";

  }
}

# sessions
{
  $_SESSION['foo'] = 'beep boop';
  session('bar', 'boop beep');
  assert(session('foo') === 'beep boop');
  assert(session('bar') === 'boop beep');
  assert(session('lol') === null);
}

# attachments
{
  # single file upload
  $_FILES['photo'] = [
    'name' => 'photo.png',
    'type' => 'image/png',
    'size' => 5120,
    'tmp_name' => '/tmp/tmpfile123',
    'error' => UPLOAD_ERR_OK
  ];

  # array upload
  $_FILES['thumbs'] = [
    'name' => ['thumb1.png', 'thumb2.png', 'thumb3.png'],
    'type' => ['image/png', 'image/png', 'image/png'],
    'size' => [5120, 5121, 5122],
    'tmp_name' => ['/tmp/upload1', '/tmp/upload2', '/tmp/upload3'],
    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_OK]
  ];

  # check single file upload
  assert($_FILES['photo'] === attachments('photo'));

  # check array upload
  assert(attachments('thumbs')[0] === [
    'name' => 'thumb1.png',
    'type' => 'image/png',
    'size' => 5120,
    'tmp_name' => '/tmp/upload1',
    'error' => UPLOAD_ERR_OK
  ]);

  # check for invalids
  assert(attachments('invalid') === null);
}

# php://input loader
{
  # load mock file directly
  list($_, $data) = input(true, __DIR__.'/fixtures/sample.json');
  assert(trim($data) === json_encode(['name' => 'dispatch']));

  # put mock file into temp file
  list($_, $path) = input(false, __DIR__.'/fixtures/sample.json');
  assert(file_exists($path));
  assert(file_get_contents($path) === $data);
}

# route mapping tests
{
  # global container for routes
  $routes = &$GLOBALS['noodlehaus\dispatch']['routes'];

  # 3-arg mappings
  map('GET', '/route1', 'cb1');
  assert(in_array(['/route1', 'cb1'], $routes['explicit']['GET']));
  map(['GET', 'POST'], '/route2', 'cb2');
  assert(in_array(['/route2', 'cb2'], $routes['explicit']['GET']));
  assert(in_array(['/route2', 'cb2'], $routes['explicit']['POST']));

  # 2-arg mappings
  map('/route3', 'cb3');
  assert(in_array(['/route3', 'cb3'], $routes['any']));

  map(['/route4-1', '/route4-2'], 'cb4');
  assert(in_array(['/route4-1', 'cb4'], $routes['any']));
  assert(in_array(['/route4-2', 'cb4'], $routes['any']));

  # error mapping
  map(404, 'cb5');
  assert($routes['errors'][404] === 'cb5');
  map([400, 401], 'cb6');
  assert($routes['errors'][400] === 'cb6');
  assert($routes['errors'][401] === 'cb6');

  # 1-arg mapping
  map('cb7');
  assert($routes['all'] === 'cb7');

  # invalid map call
  try {
    map();
  } catch (Exception $e) {
    assert($e instanceof BadFunctionCallException);
  }

  # hook mapping tests
  hook('id', 'hook1');
  assert($routes['hooks']['id'] === 'hook1');
}

# request dispatch tests
{
  # error handler to test (we get code, then dispatch args)
  map(404, function ($code, $d1, $d2) {
    assert(http_response_code() === 404);
    assert($code === 404);
    assert($d1 === 'dargs1');
    assert($d2 === 'dargs2');
  });

  # hook to test
  hook('p1', function ($p1) {
    return strtoupper($p1);
  });

  # route to test
  map('POST', '/disp1/{p1}', function ($params, $d1, $d2) {
    assert($params['p1'] === 'PARAM1');
    assert($d1 === 'dargs1');
    assert($d2 === 'dargs2');
  });

  # invalid mapping setup (will also trigger 404)
  map('GET', '/disp2', 'foo');

  # setup fake request
  $_SERVER = [
    'REQUEST_URI' => '/disp1/param1',
    'REQUEST_METHOD' => 'POST'
  ];

  # dispatch
  dispatch('dargs1', 'dargs2');

  # setup fake failing request
  $_SERVER = [
    'REQUEST_URI' => '/not-found',
    'REQUEST_METHOD' => 'GET'
  ];

  # dispatch
  dispatch('dargs1', 'dargs2');

  # setup fake request for invalid mapping
  $_SERVER = [
    'REQUEST_URI' => '/disp2',
    'REQUEST_METHOD' => 'GET'
  ];

  # dispatch
  dispatch('dargs1', 'dargs2');

  # test invalid error trigger
  try {
    error();
  } catch (Exception $e) {
    assert($e instanceof BadFunctionCallException);
  }
}

echo "Done running tests. If you don't see errors, it means all's ok.\n";

# helper for getting and resetting headers
function headers_get_clean() {
  $list = xdebug_get_headers();
  header_remove();
  return $list;
}
