<?php
include '../src/dispatch.php';

// custom error handler
function oh_crap($errno, $errstr, $errfile, $errline) {
  echo "Test failed at line [{$errline}]\n";
  exit($errno);
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

// if we have mcrypt, test encrypt/decrypt
if (extension_loaded('mcrypt')) {
  config('cookies.secret', 'crypt-token-secret');
  $str = encrypt('jaydee');
  assert($str !== 'jaydee');
  assert(decrypt($str) === 'jaydee');
}

// test from()
$_POST = array('name' => 'jaydee', 'age' => 34);
assert(from($_POST, 'name') === 'jaydee');
$user = from($_POST, array('name', 'age'));
assert($user['name'] === 'jaydee' && $user['age'] === 34);
assert(from($_POST, 'doesnotexist') === null);

// test stash()
stash('name', 'jaydee');
assert(stash('name') === 'jaydee');
assert(stash('age') === null);

// test warn()
warn('name', 'Invalid name');
assert(warn() === 1);
assert(warn('name') === 'Invalid name');
$warnings = warn('*');
assert($warnings['name'] === 'Invalid name');

// test _u()
assert(_u('/') === '%2F');

// test _h()
assert(_h('&') === '&amp;');

// testing render and partial
config('views.root', './views');

ob_start();
render('index', array('name' => 'jaydee'));
$str1 = ob_get_clean();
$str2 = partial('partial', array('name' => 'jaydee'));
assert($str1 === 'hello, jaydee');
assert($str2 === 'hello, jaydee');

// fake request URI and METHOD
$REQUEST_URI = '/index';
$REQUEST_METHOD = 'GET';

// testing routes and symbols
get('/index', function () {
  global $REQUEST_URI;
  assert($REQUEST_URI === '/index');
});

get('/index/:name', function ($name) {
  global $REQUEST_URI;
  assert($REQUEST_URI === "/index/{$name}");
  stash('name', 'sheryl');
});

// testing route filters
filter('name', function ($name) {
  assert($name === 'sheryl');
  stash('kid', 'addie');
});

// test before
before(function () {
  stash('before', true);
});

// test after
after(function () {
  stash('after', true);
});

// invoke routes
dispatch('GET', $REQUEST_URI = '/index');
dispatch('GET', $REQUEST_URI = '/index/sheryl');

// check handler execution
assert(stash('name') === 'sheryl');
assert(stash('kid') === 'addie');
assert(stash('before') === true);
assert(stash('after') === true);

// we'll assert against this
$TOKEN = '';

// test restify
class A {
  public function onIndex() {
    global $REQUEST_URI, $REQUEST_METHOD, $TOKEN;
    assert($REQUEST_URI === '/a/index');
    assert($REQUEST_METHOD === 'GET');
    $TOKEN = 'index';
  }
  public function onNew() {
    global $REQUEST_URI, $REQUEST_METHOD, $TOKEN;
    assert($REQUEST_URI === '/a/new');
    assert($REQUEST_METHOD === 'GET');
    $TOKEN = 'new';
  }
  public function onCreate() {
    global $REQUEST_URI, $REQUEST_METHOD, $TOKEN;
    assert($REQUEST_URI === '/a/create');
    assert($REQUEST_METHOD === 'POST');
    $TOKEN = 'create';
  }
  public function onShow($id) {
    global $REQUEST_URI, $REQUEST_METHOD, $TOKEN;
    assert($id === '1');
    assert($REQUEST_URI === '/a/1/show');
    assert($REQUEST_METHOD === 'GET');
    $TOKEN = 'show';
  }
  public function onEdit($id) {
    global $REQUEST_URI, $REQUEST_METHOD, $TOKEN;
    assert($id === '1');
    assert($REQUEST_URI === '/a/1/edit');
    assert($REQUEST_METHOD === 'GET');
    $TOKEN = 'edit';
  }
  public function onUpdate($id) {
    global $REQUEST_URI, $REQUEST_METHOD, $TOKEN;
    assert($id === '1');
    assert($REQUEST_URI === '/a/1');
    assert($REQUEST_METHOD === 'PUT');
    $TOKEN = 'update';
  }
  public function onDelete($id) {
    global $REQUEST_URI, $REQUEST_METHOD, $TOKEN;
    assert($id === '1');
    assert($REQUEST_URI === '/a/1');
    assert($REQUEST_METHOD === 'DELETE');
    $TOKEN = 'delete';
  }
}

restify('/a', new A());

// invoke the routes
dispatch($REQUEST_METHOD = 'GET', $REQUEST_URI = '/a/index');
assert($TOKEN === 'index');
dispatch($REQUEST_METHOD = 'GET', $REQUEST_URI = '/a/new');
assert($TOKEN === 'new');
dispatch($REQUEST_METHOD = 'POST', $REQUEST_URI = '/a/create');
assert($TOKEN === 'create');
dispatch($REQUEST_METHOD = 'GET', $REQUEST_URI = '/a/1/show');
assert($TOKEN === 'show');
dispatch($REQUEST_METHOD = 'GET', $REQUEST_URI = '/a/1/edit');
assert($TOKEN === 'edit');
dispatch($REQUEST_METHOD = 'PUT', $REQUEST_URI = '/a/1');
assert($TOKEN === 'update');
dispatch($REQUEST_METHOD = 'DELETE', $REQUEST_URI = '/a/1');
assert($TOKEN === 'delete');

echo "all tests passed!\n";
?>
