<?php declare(strict_types=1);

require __DIR__.'/../dispatch.php';

# Test stash
stash('test_key', 'test_value');
assert(stash('test_key') === 'test_value', 'stash: setting and getting a value failed');

# Test dispatch, route, response
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/test';
route('GET', '/test', fn() => response('Hello, World!'));

ob_start();
dispatch();
$output = ob_get_clean();
assert($output === 'Hello, World!', 'dispatch: failed to dispatch the correct route');

# Test _404
$custom404Called = false;
_404(function () use (&$custom404Called) {
  $custom404Called = true;
  return response('Custom 404', 404);
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/non-existent-route';

ob_start();
dispatch();
$output = ob_get_clean();
assert($output === 'Custom 404' && $custom404Called, '_404: failed to handle non-matching route with custom 404');

# Test apply and middleware
$middlewareCalled = false;
$middleware = function ($next) use (&$middlewareCalled) {
  $middlewareCalled = true;
  return $next();
};

apply('/test-middleware', $middleware);
$_SERVER['REQUEST_URI'] = '/test-middleware';
route('GET', '/test-middleware', fn() => response('Middleware Applied!'));

ob_start();
dispatch();
$output = ob_get_clean();
assert($output === 'Middleware Applied!', 'dispatch: failed to dispatch the route with middleware');
assert($middlewareCalled, 'apply: middleware was not called');

# Test bind
bind('id', fn($value) => intval($value));
route('GET', '/bind/:id', fn($params) => response('Bound value: ' . $params['id']));

$_SERVER['REQUEST_URI'] = '/bind/42';
ob_start();
dispatch();
$output = ob_get_clean();
assert($output === 'Bound value: 42', 'bind: failed to bind and transform the parameter');

# Test redirect
route('GET', '/redirect', fn() => redirect('/test-redirect'));

$_SERVER['REQUEST_URI'] = '/redirect';
ob_start();
dispatch();
$output = ob_get_clean();
assert(empty($output) && http_response_code() === 302, 'redirect: failed to create a redirect response');

# Test phtml
file_put_contents('test_template.phtml', 'Hello, <?= $name ?>!');
$output = phtml('test_template', ['name' => 'World']);
assert($output === 'Hello, World!', 'phtml: failed to render and return the content of a template');

# Clean up the test template file
unlink('test_template.phtml');

echo "All tests passed!" . PHP_EOL;

