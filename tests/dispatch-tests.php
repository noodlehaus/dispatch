<?php

require __DIR__.'/../dispatch.php';

test_response();
test_redirect();
test_action();
test_match();
test_serve();
test_context();
test_phtml();
test_page();
test_route();
test_dispatch();

# response()
function test_response() {
  $resp = response('foo', 200, ['content-type' => 'text/plain']);
  assert($resp === ['foo', 200, ['content-type' => 'text/plain']]);
}

# redirect()
function test_redirect() {
  assert(redirect('/index') === ['', 302, ['location' => '/index']]);
  assert(redirect('/index', 301) === ['', 301, ['location' => '/index']]);
}

# action()
function test_action() {
  $f1 = action('GET', '/index', function () {
    return 'index';
  });
  $f2 = action('GET', '/:name/:location', function ($args) {
    return $args['name'];
  });
  assert(is_callable($f1) && is_callable($f2));
  assert(
    empty($f1('POST', '/index')) &&
    empty($f1('GET', '/about')) &&
    empty($f2('POST', '/about')) &&
    empty($f2('GET', '/about/bleh/moo'))
  );
  list($c1, $v1) = $f1('GET', '/index');
  list($c2, $v2) = $f2('GET', '/dispatch/singapore');
  assert($c1() === 'index' && empty($v1));
  assert($c2($v2) === 'dispatch' && isset($v2['name'], $v2['location']));
}

# match()
function test_match() {
  $v = [
    action('GET', '/index', function () { return 'GET index'; }),
    action('POST', '/index', function () { return 'POST index'; })
  ];
  assert(empty(match($v, 'GET', '/about')));
  list($f, $c) = match($v, 'POST', '/index');
  assert(empty($c) && $f() === 'POST index');
}

# serve()
function test_serve() {
  $v = [
    action('GET', '/index', function () { return response('GET index'); }),
    action('POST', '/index', function ($d) { return response("POST {$d}"); })
  ];
  $r = serve($v, 'POST', '/index', 'foo');
  assert(count($r) === 3);
  assert($r[0] === 'POST foo' && $r[1] === 200 && $r[2] === []);
}

# context()
function test_context() {
  $v = &context();
  $v[] = 'foo';
  $x = &context();
  assert($x === ['foo']);
  array_shift($x);
  assert(empty(context()));
}

# phtml
function test_phtml() {
  $t = phtml(__DIR__.'/test-phtml', ['name' => 'world']);
  assert($t === 'hello, world!');
}

# page()
function test_page() {
  $f = page(__DIR__.'/test-phtml', ['name' => 'world']);
  assert(is_callable($f) && $f() === response('hello, world!', 200, []));
}

# route()
function test_route() {
  route('GET', '/index', function () {
    return response('hello world!', 201, ['X-Custom-Value' => 'foo']);
  });
  $c = context();
  $m = $c[0];
  assert(is_callable($m));
  list($f, $v) = $m('GET', '/index');
  assert(empty($v) && $f() === response(
    'hello world!', 201, ['X-Custom-Value' => 'foo']
  ));
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
