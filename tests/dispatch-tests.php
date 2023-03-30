<?php

require __DIR__.'/../dispatch.php';

test_response();
test_redirect();
test_action();
test_serve();
test_stash();
test_phtml();
test_page();
test_route();
test_bind();
test_middleware();
test_dispatch();

# response()
function test_response() {
  is_callable(response('foo', 200, ['content-type' => 'text/plain']));
}

# redirect()
function test_redirect() {
  assert(is_callable(redirect('/index')));
  assert(is_callable(redirect('/index', 301)));
}

# action()
function test_action() {
  $f1 = action('GET', '/index', function () {
    return 'index';
  });
  $f2 = action('GET', '/:name/:location', function ($args) {
    return $args['name'];
  });
  assert(count($f1) === 3);
  assert(count($f2) === 3);
  assert(preg_match($f1[1], '/index'));
  assert(preg_match($f2[1], '/dispatch/singapore'));
  assert($f1[0] === 'GET');
  assert($f2[0] === 'GET');
  $v = [
    action('GET', '/index', function () { return response('GET index'); }),
    action('POST', '/index', function ($d) { return response("POST {$d}"); })
  ];
  $r = serve($v, 'POST', '/index', 'foo');
  assert(is_callable($r));
}

# serve()
function test_serve() {
  $v = [
    action('GET', '/index', function () { return response('GET index'); }),
    action('POST', '/index', function ($d) { return response("POST {$d}"); })
  ];
  $r = serve($v, 'POST', '/index', 'foo');
  assert(is_callable($r));
}

# context()
function test_stash() {
  stash('name', 'noodlehaus');
  assert(stash('name') === 'noodlehaus');
}

# phtml
function test_phtml() {
  $t = phtml(__DIR__.'/test-phtml', ['name' => 'world']);
  assert($t === 'hello, world!');
}

# page()
function test_page() {
  $f = page(__DIR__.'/test-phtml', ['name' => 'world']);
  assert(is_callable($f) && is_callable($f()));
}

# route()
function test_route() {

  $action = function () {
    return response('hello world!', 201, ['X-Custom-Value' => 'foo']);
  };
  route('GET', '/index', $action);
  $routes = stash(DISPATCH_ROUTES_KEY);
  list($method, $expr, $handler) = $routes[0];
  assert($method === 'GET');
  assert(preg_match($expr, '/index'));
  assert(is_array($handler) && $handler[0] === $action);
}

# bind()
function test_bind() {
  bind('name', fn($name) => strtoupper($name));
  route('GET', '/greet/:name', function ($params) {
    return response("hello, {$params['name']}!");
  });
  $_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/greet/wednesday'
  ];
  ob_start();
  dispatch();
  assert(trim(ob_get_clean()) === 'hello, WEDNESDAY!');
}

# middleware
function test_middleware() {
  $middleware = function ($next) {
    stash('foo', 'bar');
    return $next();
  };
  route('GET', '/foo', $middleware, function () {
    $foo = stash('foo');
    return response("hello, {$foo}!");
  });
  $_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/foo'
  ];
  ob_start();
  dispatch();
  assert(trim(ob_get_clean()) === 'hello, bar!');
}

# dispatch() - minus header and status check
function test_dispatch() {
  $_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/index'
  ];
  ob_start();
  dispatch();
  assert(trim(ob_get_clean()) === 'hello world!');
}
