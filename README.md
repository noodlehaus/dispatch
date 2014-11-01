# DISPATCH 5.x

Dispatch is a PHP micro-framework. It has just enough PHP for getting small
apps up and running quickly.

## Requirements

* php 5.4
* php-xdebug (for running complete tests)

## Rewrite Rules

Dispatch requires that all requests be routed through a front controller.
With Apache, you can do this via `mod_rewrite` and with the following config
with `index.php` as your front-controller.

```
<IfModule mod_rewrite.c>
  RewriteEngine on
  # in this case, our app bootstrap file is index.php
  RewriteRule !\.(js|html|ico|gif|jpg|png|css)$ index.php
</IfModule>
```

For Nginx, your server block can use a similar setup as the following.

```
server {
  location = / {
    try_files @site @site;
  }

  location / {
    try_files $uri $uri/ @site;
  }

  #return 404 for all php files as we do have a front controller
  location ~ \.php$ {
    return 404;
  }

  # forward the requests to php-fpm
  location @site {
    fastcgi_pass unix:/var/run/php-fpm/www.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    #uncomment when running via https
    #fastcgi_param HTTPS on;
  }
}
```

## Backward Compatibility to 4.x

Apps written using Dispatch 4.x will no longer work with 5+.

## Running Tests

Dispatch makes use of ad hoc tests using PHP's `assert()` function. Run the
following command to run them.

```
php tests/dispatch-tests.php
```

If you don't have `php-xdebug` installed, some of the tests will be skipped
(specifically, the header tests).

## Short Example

```php
<?php
require __DIR__.'/dispatch.php';

# handle specific error codes
map([400, 404, 500], function ($code) {

  $code = intval($code);

  switch ($code) {

  case 404:
    echo "Page not found";
    break;

  case 400:
    echo "Bad request";
    break;

  default:
    echo "Internal server error";
    break;
  }
});

# hook for transforming value for {uid}
hook('uid', function ($uid, $db) {
  $uid = strtolower(trim($uid));
  return isset($db[$uid]) ? $db[$uid] : null;
});

# map route handler for any method
map('/users/{uid}', function ($args, $db) {

  # $args['uid'] has the db row (from the hook) or null
  if ($row = $args['uid'])
    return print "user: {$row[0]}, {$row[1]}, {$row[2]}\n";

  # trigger our 404 handler
  return error(404);
});

# arguments to dispatch() gets forwarded too
dispatch($db = [
  'u01' => ['anna', 28, 'f'],
  'u02' => ['rein', 33, 'f'],
  'u03' => ['john', 27, 'm'],
  'u04' => ['tina', 31, 'f'],
  'u05' => ['alex', 36, 'm']
]);
```

## API Documentation

### Library Settings

Dispatch only has the following settings that can be changed via the
config files, loaded through the `settings()` function.

```ini
; path for the location of the templates
templates = ../views

; url for the application (in case it's in a subdir)
url = http://localhost/myapp

; if you don't have rewrite functionality
router = index.php
```

`templates` tells Dispatch to look inside this path when loading `.phtml`
files via `phtml()`.

`url` tells Dispatch about your app's base URL and starts routing from
that path (strips out any subdirectory before routing).

`router` tells Dispatch to remove the routing file from the requests, for
cases where you can't make use of rewrites (ie. `/index.php/foo/bar`).

### Mapping Route and Error Handlers

Create routes using the `map()` function. The following are valid
examples.

```php
<?php
# standard route -- a method, a path, an action
map('GET', '/index', 'index_action');

# multiple methods on a path
map(['GET', 'POST'], '/index', 'index_action');

# any method against a path
map('/index', 'index_action');

# action for any method and path
map('catch_all_action');

# multiple paths against an action
map(['/index', '/about'], 'index_action');

# route symbols are placed into $args
map('GET', '/greet/{name}', function ($args) {});
```

Error handlers can be created with `map()` as well. Call it with
two arguments -- an array of codes, or a single code, and the callable.

```php
<?php
# single code against a routine
map(404, 'not_found');

# multiple codes against a routine
map([401, 402, 403], 'request_error');
```

### Method Overrides

Methods other than `POST` and `GET` are not yet well-supported by browsers.
To get around this, Dispatch also lets you use method overrides, either via
the `X-Http-Method-Override` request header, or the `$_POST['_method']`
request value.

Note that `X-Http-Method-Override` takes precedence over the `_method`
approach.

### Route Symbol Hooks

Route symbols can have hooks that modify their value during dispatch.
These hooks can be created with `hook()`.

```php
<?php
# call when matching route has the url_id symbol
hook('url_id', function ($url_id) {
  # what we return is used as the new value
  return md5($url_id);
});

# route that triggers the url_id hook
map('GET', '/urls/{url_id}', function ($args) {
  # the value for url_id has been updated by our hook
  $url_id = $args['url_id'];
  print("{$url_id} is the md5() of the original \$url_id");
});
```

### Triggering HTTP Errors

To trigger an http error code and execute any mapped error handlers, return
`error()` from within your action, passing to it the http error code
you want to trip, along with whatever parameters you want to pass.

```php
<?php
# example 404 error handler
map(404, function () {

  $argv = func_get_args();
  $code = array_shift($argv);

  # $argv now has all other args other than the http code
});

# route handler that triggers a 404
map('/not-found', function ($di) {

  # let's trip the 404 handler, and pass the $di var
  return error(404, $di);
});

# our fake di container is just an array, passed during dispatch
dispatch($di = array());
```

### Dispatching and Dependency Passing

Once you have your actions and error handlers in place, you can serve the
current request via `dispatch()`.

```php
<?php
# basic dispatch
dispatch();

# dispatch with dependencies passed around
dispatch($arg1, $arg2, ...$argN);
```

DISPATCH allows you to pass dependencies to your route handlers, error
handlers, and hooks. To pass a dependency, use it as an argument to
`dispatch()`.

For actions/route handlers, if the route has symbol values, the `$args`
hash get injected first, then the arguments from `dispatch()` get passed
next.

```php
<?php
# route has a symbol, so we get $parmas first, then the dispatch() args
map('GET', '/greet/{name}', function ($args, $config) {
  # ...
});

# ex. we have a config object as a dependency
$config = parse_ini_file('config.ini');

# pass it along
dispatch($config);
```

For symbol hooks, you will get the symbol value as first argument, then
the injected dependencies next.

```php
<?php
# sample hook that gets the value first, then the injected ones
hook('name', function ($name, $config, $db) {
  # ...
});

# sample config dependency
$config = parse_ini_file('config.ini');

# sample db dependency
$db = db_connect($config);

# pass dependencies around
dispatch($config, $db);
```

For error handlers, we get the error code first, then the injected args
next.

```php
<?php
# we get the http error code first, then the injected ones
hook(404, function ($code, $db) {
  # ...
});

# ex. we have a db connection
$db = db_connect();

# pass along the db
dispatch($db);
```

Note that the order in which the dependencies are passed around is the
same as when they were passed to `dispatch()`. You can also choose to
ignore these arguments from the right, as php allows it.

### Redirects

Redirect headers can be sent via the `redirect()` function. This
function only sends out the HTTP headers and doesn't stop your script from
executing, but has the option to behave like so.

```php
<?php
# just dump the headers with a custom code
redirect('/index', 302);

# or send the headers and halt the execution
redirect('/index', 302, $halt = true);
```

### Cookies, Session, Files, and Headers

To access values from `$_COOKIE`, `$_SESSION`, `$_FILES`, and the request
headers, you can do so with `cookies()`, `session()`,
`attachments()`, and `headers()`, respectively. If the value
is not present, you'll get a `null` back.

```php
<?php
# get a cookie
$name = cookies('name');

# get a session var
$name = session('name');

# get a header (header keys are converted to lowercase)
$type = headers('content-type');
```

For file uploads, DISPATCH will re-organize and group together the file
attributes if the file matching the name is an array.

```php
<?php
# get an uploaded file
$file = files('photo');
```

For setting values, you also use the same functions but with extra
parameters for the values (and other options).

For `cookies()`, the arguments map directly to PHP's `setcookie()`
function. For `session()`, it's just the variable name and the
value.

```php
<?php
# set a cookie value that expires after a year
cookies('name', 'noodlehaus', time() + 60 * 60 * 24 * 365, '/');

# set a session var
session('name', 'noodlehaus');
```

For `headers()`, it's a bit similar to PHP's `header()` function,
the difference is it doesn't allow non-key-value pair headers (ie.
`HTTP/1.0 200 Ok`), and the key and the value need to be passed separately.

```php
<?php
# set a response header
headers('content-type', 'text/html');

# set a header and use the rest of header()'s options
headers('location', '/index', $replace = true, $code = 301);
```

### Raw Request Body

If you need the raw data for a request (ie. for `PUT` requests), you can
use `input()` to either get the file containing the data, or to
receive the data contents (for small sizes). This returns an array in the
`[content-type, path_or_data]` structure.

```php
<?php
# receive the type and the contents
list($type, $data) = input($load = true);

# receive the type and path to the file
list($type, $path) = input($load = false);
```

When getting the data directly, be sure to keep the data size in mind.

### Caching and JSON Responses

Use `json()` to send out JSON responses. This encodes the
object the object you pass to it, and prints out the appropriate headers
for the response.

If you want the client NOT to cache this response (or any response), you
can wrap it with `nocache()`, and it will print the appropriate
expires headers for you.

```php
<?php
# print out JSON data
json(['name' => 'noodlehaus']);

# print out no-cache headers, and json data
nocache();
json(['name' => 'noodlehaus']));
```

### Views and Helpers

To render PHP-based template files, use `phtml()`. This accepts a file path
without the `.phtml` extension. If you have `templates` configured, the file
will be loaded from that path.

The hash argument will be `extract()`ed into the template's scope as it is
rendered.

```php
<?php
# loads views/index.phtml
$page = phtml('views/index', ['name' => 'noodlehaus']);
```

In forms, we usually have the need to pre-populate a set of fields safely
by doing calls to `isset()`. For this, you can use `blanks()` to
create a hash of blanks for your form values.

```php
<?php
# create a hash of empty strings with the following keys
$fields = blanks('name', 'email', 'username');
```

Functions `ent()` and `url()` are available as shortcuts to commonly-used
template functions `htmlentities()` and `urlencode()`. The arguments to these
functions map directly to the arguments of their target PHP functions.

```php
<?php
$encoded = ent('Tom & Jerry', ENT_COMPAT, 'utf-8');
$urlsafe = url('/redirect/target');
```

### Using the Stash for Values

Use `stash()` to store and use values across function scopes.

```php
<?php
# stash a value
function foo() {
  stash('name', 'dispatch');
}

# fetch the value here
function bar() {
  $name = stash('name');
}
```

### Client IP

To get the remote IP address, use `ip()`.

```php
<?php
$ip = ip();
```

### PHP Configuration Files

To load `.ini` or `.php` configuration files, load and access their
contents using the `settings()` function.

To load a settings file, specify a filename as an argument prefixed by
the `@` symbol. Everytime `settings()` is called this way, all new values
are merged in using PHP's `array_replace_recursive()`.

```php
<?php
# initialize settings store
settings('@config.ini');
```

When using `.php` files, the file must return either an `array` or a
callable that returns one.

```php
<?php
# example array config
return [
  'app.hostname' => 'localhost',
  'app.environment' => 'development'
];
```
```php
<?php
# example callable config
return function () {
  return [
    'app.hostname' => 'localhost',
    'app.environment' => 'development'
  ];
};
```

## API List

```
function map(...$args)
function hook($name, $func)
function error($code, ...$args)
function redirect($path, $code = 302, $halt = false)
function dispatch(...$args)
function settings($name)
function ent($str, ...$args)
function url($str)
function phtml($path, $vars = [])
function blanks(...$args)
function ip()
function stash($name = null, $value = null)
function headers($name, $data = null)
function cookies()
function session($name, $value = null)
function attachments($name)
function input($load = false)
function nocache($content = null)
function json($obj, ...$args)
```

## Changes from 4.x to 5.x

Below is a comprehensive list of changes from Dispatch 4.x to 5.x. For
complete information, please read the rest of this document.

* `error()` no longer maps error handlers, just triggers http errors
* `config()` replaced with `settings()`
* `flash()` removed
* `html()` replaced with `ent()`, directly maps to `htmlentities()`
* `params()` removed, replaced by `array` argument for routes symbols
* `cookie()` renamed to `cookies()`, better maps to `setcookie()`
* `request_headers()` merged into `headers()`
* `is_xhr()` removed
* `request_body()` replaced by `input()`
* `files()` replaced by `attachments()`
* `send()` removed
* `scope()` replaced by `stash()`
* `redirect()` 3rd argument no longer a condition, but a halt flag
* `content()` removed
* `template()` removed
* `render()` removed
* `inline()` removed
* `partial()` replaced by `phtml()`
* `nocache()` now accepts an optional content parameter
* `json()` no longer supports the `$func` parameter
* `filter()` and `bind()` removed, replaced by `hook()`
* `before()` removed
* `after()` removed
* `on()` renamed to `map()`, and changes in behavior
* `dispatch()` changed to no longer accept method and URI

## Contributors

The current, and previous versions of Dispatch make use of code
contributed by the following persons.

* Kafene [kafene](https://github.com/kafene)
* Martin Angelov [martingalv](https://github.com/martinaglv)
* Lars [larsbo](https://github.com/larsbo)
* 4d47 [4d47](https://github.com/4d47)
* Bastian Widmer [dasrecht](https://github.com/dasrecht)
* Lloyd Zhou [lloydzhou](https://github.com/lloydzhou)
* darkalemanbr [darkalemanbr](https://github.com/darkalemanbr)
* Amin By [xielingwang](https://github.com/xielingwang)
* Ross Masters [rmasters](https://github.com/rmasters)
* Tom Streller [scan](https://github.com/scan)
* nmcgann [nmcgann](https://github.com/nmcgann)
* Ciprian Danea [cdanea](https://github.com/cdanea)
* Roman Ožana [OzzyCzech](https://github.com/OzzyCzech)

## LICENSE
MIT <http://noodlehaus.mit-license.org/>
