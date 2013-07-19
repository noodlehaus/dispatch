Dispatch 2
==========
**NOTE**: If you're looking for Dispatch 1.x, switch to the
[**1.x branch**](https://github.com/noodlehaus/dispatch/tree/1.x).

Dispatch is another PHP micro-framework. It's very small (22 functions at
the moment) and very straightforward to use. No classes, no namespaces.

## Requirements
Dispatch requires at least **PHP 5.4** to work.

## Installation
To install using `composer`, have the following lines in your `composer.json` file.

```javascript
{
  "require": {
    "php": ">= 5.4.0",
    ...
    "dispatch/dispatch": "2.0.*",
  }
}
```

Then do a `composer install` or `composer update` to install the package.

If you don't use `composer`, just download and include `dispatch.php` directly in
your application.

Note that Dispatch functions are all loaded into the global namespace.

## Configuration Variables

Some settings are needed by Dispatch, and they can be set via `config()`.

```php
<?php
// REQUIRED, base path for your views
config('dispatch.views', '../views');

// REQUIRED, default layout to use (omit .html.php extension)
config('dispatch.layout', 'layout');

// REQUIRED, cookie name to use for flash messages
config('dispatch.flash_cookie', '_F');

// OPTIONAL, specify your app's full URL
config('dispatch.url', 'http://somedomain.com/someapp/path');

// OPTIONAL, routing file to be taken off of the request URI
config('dispatch.router', 'index.php');
?>
```

## Routing
Application routes are created via calls to `on($method, $path, $callback)`. Supported methods are
`GET`, `POST`, `PUT`, `DELETE`, `HEAD` and `PATCH`. The `$method` parameter can be a single method, an
array of methods, or `*` (for all methods).

```php
<?php
// get route for index
on('GET', '/index', function () {
  echo "hello, world!\n";
});

// support the route for multiple methods
on(['GET', 'POST'], '/greet', function () {
  echo "hello, world!\n";
});

// handle all supported methods for a route (get, post, put, delete, head, patch)
on('*', '/multi', function () {
  echo "it works!\n";
});
?>
```

## Site Path and URL Rewriting
If your app resides in a subfolder, include this path in your `dispatch.url` setting, so Dispatch
knows which parts of the `REQUEST_URI` need to be removed. This URL or your app path can then be
accessed via `site($path_only = false)`.

```php
<?php
// our app lives in /mysite
config('dispatch.url', 'http://somehost.com/mysite');

on('GET', '/users', function () {
  echo "listing users...";
});

// requested URI = http://somehost.com/mysite/users
// response = "listing users..."

// get your full URL
$url = site();

// get just /mysite
$path = site(true);
?>
```

If you don't have access to URL rewrites, and are using a file router (ie. /index.php/controller/action),
you need to specify this via `dispatch.router`. The string you set this to gets stripped off of the
`REQUEST_URI` before Dispatch routes the request.

```php
<?php
// strip index.php from all route requests
config('dispatch.router', 'index.php');

on('GET', '/users', function () {
  echo "listing users...";
});

// requested URI = /index.php/users
// response = "listing users..."
?>
```

## HTTP Redirects
Redirects are done via `redirect($path, $code = 302, $condition = true)`. The third
parameter, `$condition`, is useful if you want your redirects to happen depending on
the result of an expression.

```php
<?php
// basic redirect
redirect('/index');

// with a custom code
redirect('/new-url', 301);

// redirect if authenticated() is false
redirect('/denied', 302, !authenticated());
?>
```

## Request Method Overrides
Until browsers provide support for DELETE and PUT methods in forms,
you can instead use a hidden input field named `_method` to override
the request method for the form.

```html
<!-- sample PUT request -->
<form method="POST" action="/users/1">
  <input type="hidden" name="_method" value="PUT">
  ...
  <input type="submit" value="Update">
</form>

<!-- sample DELETE request -->
<form method="POST" action="/users/1">
  <input type="hidden" name="_method" value="DELETE">
  ...
  <input type="submit" value="Remove">
</form>
```

## Request Body in PUTs or JSON POSTs
In cases where you're handling PUT requests or JSON posts and you need access
to the raw http request body contents, you can use `request_body()`.

For content of type `application/json` and `application/x-www-form-urlencoded`,
the content are automatically parsed and returned as arrays.

```php
<?php
on('PUT', '/users/:id', function ($id) {
  $data = request_body();
});
?>
```

## Route Symbol Filters
Route filters let you map functions against symbols in your routes. These functions get executed when
those symbols are found in the request's matching route.

```php
<?php
// preload blog entry whenever a matching route has :blog_id in it
filter('blog_id', function ($blog_id) {
	$blog = Blog::findOne($blog_id);
	// stash() lets you store stuff for later use (NOT a cache)
	stash('blog', $blog);
});

// here, we have :blog_id in the route, so our preloader gets run
on('GET', '/blogs/:blog_id', function ($blog_id) {
	// pick up what we got from the stash
	$blog = stash('blog');
	render('blogs/show', array('blog' => $blog);
});
?>
```

## Before and After Callbacks
To setup routines to be run before and after a request, use `before($callable)` and `after($callable)`
respectively.

```php
<?php
// setup a function to be called before each request
before(function () {
  // setup stuff
});

// setup a function to be called after each request
after(function () {
  // clean up stuff
});
?>
```

## HTTP Errors and Error Handling
You can create custom HTTP error handlers and trigger them as well via calls to
`error($code, $callback_or_string = null)`.

```php
<?php
// create a 404 handler
error(404, function () {
  echo "Oops!\n";
});

// trigger the error
error(404);

// trigger another error, with a custom message
error(500, "Something broke!");
?>
```

## Views and Partials
Dispatch expects views and partials to have extensions of `.html.php`.

To dump a view, make a call to `render($path, $locals = [], $layout = null)`.
If the third argument, `$layout` is set to `false`, then no layout file will be used.
Anything else, it's treated as a filename inside your views folder.

Note that execution stops after `render()` completes.

```php
<?php
// configure our views folder
config('dispatch.views', '../views');

// render a template, using some local variables
render('users/profile', array('name' => 'jaydee', 'age' => 35));

// .. or ..

// render a template, using some local variables
render('users/profile', array('name' => 'jaydee', 'age' => 35), 'custom-layout');
?>
```

For partials, these files are expected to begin with the `_` character, and can be
loaded via `partial($path, $locals = [])`.

```php
<?php
// underscore on the filename is added automatically by partial()
$html = partial('users/profile_page', array('data' => $data));
?>
```

## JSON and JSONP Responses
JSON and JSONP responses are done via `json_out($obj, $func = null)`.

```php
<?php
// object to dump
$obj = ['name' => 'noodlehaus', 'age' => 34];

// non-cacheable json response
json_out($obj);

...

// jsonp callback name
$fxn = 'parseResponse';

// non-cacheable jsonp response
jsonp_out($fxn, $obj);
?>
```

## No-Cache
If you want to output non-cacheable content, you can do this by calling `nocache()` before
outputting any content.

```php
<?php
// output nocache headers
nocache();

echo "comes fresh, everytime!";
?>
```

## Cookies
Get and set cookie values via `cookie($name, $value = null, $expire = 0, $path = '/')`.

```php
<?php
// set a cookie
cookie('user_id', 'user-12345');

// get a cookie
$user_id = cookie('user_id');
?>
```

## Cross-Request Messages (Flash)
Cross-request messages, or flash messages, can be done via `flash($name, $message = null, $now = false)`.

```php
<?php
// set an error message to show after a redirect
flash('error', 'You did something wrong!');
redirect('/some-page');

// .. then on your other page ..

$message = flash('error');
?>
```

## $\_GET, $\_POST Values and Route Symbols
To fetch a value from a request without regard to wether it comes from `$_GET`,
`$_POST`, or the route symbols, use `params($name)`. This is just like Rails' `params` hash.

```php
<?php
// get 'name' from $_GET, $_POST or the route symbols
$name = params('name');

// get 'name', set a default value if not found
$name = params('name', 'stranger');
?>
```

## $\_FILES Values
During file uploads, to get consolidated info on the file, call `upload($name)`, where `$name`
is the name of the file input field. If the file input field is an array, info is grouped
conveniently by file and returned as an array.

```php
<?php
// get info on an uploaded file
$file = upload('photo');
?>
```

## Loading INI Files
You can make use of ini files for configuration by calling `config('source', 'myconfig.ini')`.

```php
<?php
// load the contents of my-settings.ini into config()
config('source', 'my-settings.ini');

// load another ini file, merge it with the previous one
config('source', 'my-other-settings.ini');

// get a config value from the loaded configs
$secret = config('some.setting');
?>
```

## Utility Functions
Some utility functions are also provided - for getting the client's IP,
for making a string HTML-safe, for making a string URL-safe, and for
setting/fetching values cross-scope.

```php
<?php
// get the client's ip
$ip = ip();

// store a value that can be fetched later
scope('user', $user);

// fetch a stored value
scope('user');

// client's IP
$ip = client_ip();

// escape a string's entities
h('Marley & Me');

// make a string url-safe
u('http://noodlehaus.github.com/dispatch');
?>
```

## Function Catalog
Below's the list of functions provided by Dispatch.

```php
<?php
function error($code, $callback = null)
function config($key, $value = null)
function site($path_only = false)
function flash($key, $msg = null, $now = false)
function u($str)
function h($str, $flags = ENT_QUOTES, $enc = 'UTF-8')
function params($name = null, $default = null)
function cookie($name, $value = null, $expire = 0, $path = '/')
function request_body()
function upload($name)
function scope($name, $value = null)
function ip()
function redirect($path, $code = 302, $condition = true)
function partial($view, $locals = null)
function render($view, $locals = null, $layout = null)
function nocache()
function json_out($obj, $func = null)
function filter($symbol, $callback = null)
function before($callback = null)
function after($callback = null)
function on($method, $path, $callback = null)
function dispatch($method = null, $path = null)
?>
```

## About the Author

Dispatch is written by [Jesus A. Domingo].

[Jesus A. Domingo]: http://github.com/noodlehaus

## Credits and Contributors

The following projects served as both references and inspirations for Dispatch:

* [ExpressJS](http://expressjs.com)
* [Sinatra](http://sinatrarb.com)
* [BreezePHP](http://breezephp.com)

Thanks to the following contributors for helping improve this tool :)

* Kafene [kafene](https://github.com/kafene)
* Martin Angelov [martingalv](https://github.com/martinaglv)
* Lars [larsbo](https://github.com/larsbo)

## LICENSE
MIT <http://noodlehaus.mit-license.org/>
