Dispatch 2.0.0
==============
Dispatch is another PHP micro-framework. It's very small (23 functions at
the moment) and very straightforward to use. No classes, no namespaces.

## Requirements
Dispatch requires at least PHP 5.4 to work. Honestly, 5.4 is required just
because of the short array syntax used in the code.

## Installation
Dispatch can be installed by using `composer`. In your `composer.json` file,
do the following:

```javascript
{
  "require": {
    "php": ">= 5.4.0",
    ...
    "dispatch/dispatch": "2.0.*",
  }
}
```

After adding the appropriate `require` entries, do a `composer install` or
`composer update` to install the package.

If you don't use `composer`, you can download and include
`dispatch.php` directly in your application.

Note that Dispatch functions are all loaded into the global namespace.

## Configuration Variables

Certain properties and behaviors of Dispatch can be configured via the
following `config()` entries:

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
Dispatch supports the `GET`, `POST`, `PUT`, `DELETE`, and `HEAD` request methods.

```php
<?php
// get route for index
route('GET /index', function () {
  echo "hello, world!\n";
});

// this is the same as above
route('GET', '/index', function () {
  echo "hello, world!\n";
});
?>
```

## Site Path and URL Rewriting
If your app lives in a subfolder on your domain, you can include this path when
defining your site's full URL via `dispatch.url`. With this setting, you can also
access your site's URL via `site($pathonly = false)`. If you specify `$pathonly`
to be `true`, then you get just the path for your site's URL.

```php
<?php
// our app lives in /mysite
config('dispatch.url', 'http://somehost.com/mysite');

route('GET /users', function () {
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

If you don't have access to url rewriting on your server, and you're using a PHP
file as your router (ie. /index.php/controller/action), you can specify the
routing file to take off from request URIs via `dispatch.router`.

```php
<?php
// strip index.php from all route requests
config('dispatch.router', 'index.php');

route('GET /users', function () {
  echo "listing users...";
});

// requested URI = /index.php/users
// response = "listing users..."
?>
```

## HTTP Redirects
Redirects are done by calling `redirect($path, $code = 302, $condition = true)`. The third
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
Until browsers provide support for DELETE and PUT methods in their forms,
you can instead use a hidden `input` field named `_method` to override
the request method for your form.

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
to the raw http request body contents, you can use `request_body()` for this.
The `request_body()` function will return an associative array containing the
body's `type`, `length`, `parsed` and `raw`.

The `request_body()` function accepts an optional parameter, which should be a
`callable` that takes three arguments - content type, length and raw data. This
callable will be treated as the parser for the data and whatever it returns will
be used by `request_body()` as the value for `parsed`.

```php
<?php
route('PUT /users/:id', function ($id) {
  $data = request_body();
  // or
  $data = request_body(function ($type, $length, $raw) {
    return json_decode($raw);
  });
  /**
  $data will be a hash of this structure
  array(
    'type' => 'content/type-here',
    'length' => 123,
    'parsed' => 'some data',
    'raw' => 'raw data'
  )
  */
});
?>
```

## Route Symbol Filters
This is taken from ExpressJS. Route filters let you map functions against symbols
in your routes. These functions then get executed when those symbols are matched.

```php
<?php
// preload blog entry whenever a matching route has :blog_id in it
filter('blog_id', function ($blog_id) {
	$blog = Blog::findOne($blog_id);
	// stash() lets you store stuff for later use (NOT a cache)
	stash('blog', $blog);
});

// here, we have :blog_id in the route, so our preloader gets run
route('GET /blogs/:blog_id', function ($blog_id) {
	// pick up what we got from the stash
	$blog = stash('blog');
	render('blogs/show', array('blog' => $blog);
});
?>
```

## Before and After Callbacks
Dispatch also lets you setup routines that can be called before or after requests are handled.

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
If you want to output JSON or JSONP responses, you can do it via `json_out($obj, $func = null)`.
If called with just one argument, then `$obj` will be json encoded and sent over. If
the second argument, `$func`, is provided, then the response will be in JSONP format, where
`$func` is used as the callback function around the json object.

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
Dispatch provides a convenience function for getting and setting cookies,
`cookie($name, $value = null, $expire = 0, $path = '/')`. If called with just one
argument, `$name`, then it will return the value for that cookie. Otherwise,
it behaves just like PHP's `setcookie()`.

```php
<?php
// set a cookie
cookie('user_id', 'user-12345');

// get a cookie
$user_id = cookie('user_id');
?>
```

## Cross-Request Messages (Flash)
Dispatch has support for cross-request (flash) messages. This is done via calls to
`flash($name, $message = null, $now = false)`. If called with just one argument, `$name`,
then the message mapped to that name is returned. The third argument, `$now` dictates
if the message is to be made available to this request only.

```php
<?php
// set an error message to show after a redirect
flash('error', 'You did something wrong!');
redirect('/some-page');

// .. then on your other page ..

$message = flash('error');
?>
```

## $\_GET and $\_POST Values
If you want to fetch a value from a request without regard to wether it comes from `$_GET` or
`$_POST`, you can use the function `param($name)` to get this value. This is just like Rails'
`params` hash, where the `$_POST` values take priority over the `$_GET` values.

```php
<?php
// get 'name' from either $_GET or $_POST
$name = param('name');

// get 'name', set a default value if not found
$name = param('name', 'stranger');
?>
```

## $\_FILES Values
Dispatch gives you the function `upload($name)` to fetch the information on a file upload
field from the `$_FILES` superglobal. If the field is present, then the function will return
a hash containing all file information about the upload. This function also works on array
of files. Every file that passes through `upload($name)` is checked with `is_uploaded_file()`.

```php
<?php
// get info on an uploaded file
$file = upload('photo');
?>
```

## Loading INI Files
You can make use of ini files for configuration by calling `config('source', 'myconfig.ini')`.
This lets you put configuration settings in ini files instead of making `config()` calls
in your code.

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
