<?php

require __DIR__.'/../vendor/autoload.php';

test_redirect();
test_context();
test_phtml();
test_page();
test_route();
test_dispatch();

# redirect()
function test_redirect() {
  assert(redirect('/index') === ['', 302, ['location' => '/index']]);
  assert(redirect('/index', 301) === ['', 301, ['location' => '/index']]);
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
