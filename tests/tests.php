<?php
include '../src/dispatch.php';

// custom error handler
function oh_crap($errno, $errstr, $errfile, $errline) {
  echo "Test failed at line [{$errline}]\n";
}

// custom error handler
set_error_handler('oh_crap');

// custom assert
assert_options(ASSERT_ACTIVE, true);
assert_options(ASSERT_BAIL, false);
assert_options(ASSERT_QUIET_EVAL, true);
assert_options(ASSERT_WARNING, true);
assert_options(ASSERT_CALLBACK, function ($file, $line, $message) {
  oh_crap(E_USER_NOTICE, $message, $file, $line);
});

// test config
config('dummy.setting', 'dispatch');
assert(config('dummy.setting') === 'dispatch');
assert(config('does.not.exist') === null);

// test scope()
scope('name', 'jaydee');
assert(scope('name') === 'jaydee');
assert(scope('age') === null);

// test u()
assert(u('/') === '%2F');

// test h()
assert(h('&') === '&amp;');

// fake request URI and METHOD
$REQUEST_URI = '/index';
$REQUEST_METHOD = 'GET';

// testing routes and symbols
on('GET', '/index', function () {
  global $REQUEST_URI;
  assert($REQUEST_URI === '/index');
});

on('GET', '/index/:name', function ($name) {
  global $REQUEST_URI;
  assert($REQUEST_URI === "/index/{$name}");
  scope('name', 'sheryl');
});

// testing route filters
filter('name', function ($name) {
  assert($name === 'sheryl');
  scope('kid', 'addie');
});

// invoke routes
dispatch('GET', $REQUEST_URI = '/index');
dispatch('GET', $REQUEST_URI = '/index/sheryl');

// check handler execution
assert(scope('name') === 'sheryl');
assert(scope('kid') === 'addie');

// we'll assert against this
$TOKEN = '';

// test new routing format
on('GET', '/sample-route', function () {
  scope('sample-route', true);
});

dispatch('GET', '/sample-route');
assert(scope('sample-route') === true);

// test dispatch.router
config('dispatch.router', 'index.php');
scope('sample-route', false);
dispatch('GET', '/index.php/sample-route');
assert(scope('sample-route') === true);
// test dispatch.url path stripping
config('dispatch.url', 'http://localhost/myapp/');
scope('sample-route', false);
dispatch('GET', '/myapp/index.php/sample-route');
assert(scope('sample-route') === true);

// test params()
on('GET', '/aloha/:p1', function ($name) {
  assert(params('p1') === 'jaydee');
});
dispatch('GET', '/aloha/jaydee');

// if we got here, then good
echo "core-tests done!\n";
?>
