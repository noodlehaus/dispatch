## dispatch

- a tiny library for quick and easy PHP apps
- requires at least PHP 8.x

## functions

Below is the list of functions provided by `dispatch`.

```php
function dispatch(...$args): void;
function route(string $method, string $path, callable ...$handlers): void;
function _404(callable $handler = null): callable;
function apply(...$args): void;
function bind(string $name, callable $transform): void;
function action(string $method, string $path, callable ...$handlers): array;
function response(string $body, int $code = 200, array $headers = []): callable;
function redirect(string $location, int $code = 302): callable;
function serve(array $routes, string $reqmethod, string $reqpath, ...$args): callable;
function phtml(string $path, array $vars = []): string;
function stash(string $key, mixed $value = null): mixed;
```

Here's a sample of how you'd usually use them in an app.

```php
<?php

require 'path/to/dispatch.php';

# This is a named route parameter binding. If a requested URI has a
# :name parameter in the matching route (eg. /profiles/:user), the mapped
# callback gets executed, and the return value gets used as a replacement
# for the named parameter value.
bind('user', function (string $username, $db): array {
  $user = loadUserProfileByUsername($db, $username);
  return $user;
});

# Sample middleware that is applied to all routes. Note that
# the middleware function requires the first two params to be $next which
# is a callable to the next middleware, and the $params named params
# associative array. The $params array is always passed, and not optional.
# Other arguments that follow are ones forwarded from the dispatch() call.
apply(function (callable $next, array $params, $db) {
  if (isDeviceRestricted($_SERVER)) {
    # returning a response here breaks the middleware chain
    return resource('Forbidden', 403);
  }
  # we move on to the next middleware
  return $next();
});

# Sample middleware that gets applied to all routes, and also uses the
# stash() function to store values we'll need later.
apply(function ($next) {
  # stash is a function for storing values that can be accessed
  # anywhere in your handlers. values stored only lasts within the same
  # request context.
  stash('favicon.ico', file_get_contents(__DIR__.'/static/favicon.ico'));
  return $next();
});

# Sample middleware that gets applied to routes matching
# the regular expression argument.
apply('^/admin/', function ($next, $params, $db) {
  # note that because of the named parameter binding above, the
  # value of $params['user'] is already the loaded user profile
  if (!isAdmin($params['user'])) {
    return resource('Forbidden', 403);
  }
  return $next();
}

# Replace default 404 handler
_404(fn() => response(phtml('not-found'), 404));

# Sample route that has a named parameter value. Named parameters gets
# passed to the handlers as the first argument as an associative array.
# Arguments that follow the named parameters array are values passed through
# dispatch(...).
route('GET', '/profiles/:user', function (array $params, $db) {

  # because of the named param binding for user, this will
  # contain the user profile loaded by the named param handler
  $user = $params['user'];

  # the $db argument was forwarded from the dispatch() call below
  $meta = loadUserMetadata($db, $user['username']);

  # phtml() is a function that loads a phtml file and populates it with
  # values from the passed in associative array.
  return response(phtml(__DIR__.'/templates/profile', ['user' => $user]));
});

# Sample route that has no named parameter so it doesn't receive the $params
# associative array. Only dispatch() arguments get forwarded to the handler.
route('GET', '/index', function ($db) {
  $users = loadTopUsers($db);
  return response(phtml(__DIR__.'/templates/index', ['users' => $users]));
});

# Sample route that has an inline middleware passed in. Note that the
# middleware function should still follow the middleware function signature.
route(
  'GET',
  '/favicon.ico',
  # inline middleware
  function ($next, $params, $db) {
    logDeviceAccess($db, $_SERVER);
    return $next();
  },
  # this is the main handler
  function () {
    # stash is a request-scoped storage
    return response(stash('favicon.ico'));
  }
);

# App routing entry point. All arguments passed to dispatch get forwarded to
# matching route handlers after the named params array.
$db = createDatabaseConnection();
dispatch($db);

```

Once `dispatch(...)` is called, it will try to match the current request to any
of the mapped routes via `route(...)`. When it finds a match, it will then do the
following sequence:

1. Execute all named parameter bindings from `bind(...)`
2. Execute all global middleware and matching middleware from `apply(...)`
3. Invoke the handler for the matching route.

Because of this sequence, it means that any transformations done by `bind(...)`
mappings will have already updated the values inside the `$params` array that's
forwarded down the execution chain.


## license

MIT
