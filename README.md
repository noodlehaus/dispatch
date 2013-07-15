Dispatch PHP 5.3+ Micro-framework
=================================
Dispatch is a PHP micro-framework. It provides functions that wrap commonly-used tasks in creating a web app. At the very least, Dispatch provides you with URL routing and view rendering.

## Requirements
* PHP 5.3+
* `mcrypt` extension if you want to use encrypted cookies and wish to use `encrypt()` and `decrypt()` functions
* `apc` or `memcached` extension if you want to use `cache()` and `cache_invalidate()`

## Installation
Dispatch can be installed by using `composer`. In your `composer.json` file, do the following:

```javascript
{
  "require": {
    "php": ">= 5.3.0",
    ...
    "dispatch/dispatch": "*",
  }
}
```

After adding the appropriate `require` entries, do a `composer install` or `composer update` to install the package.

If you don't use `composer`, you can download and include [src/dispatch.php](https://github.com/noodlehaus/dispatch/raw/master/src/dispatch.php) directly in your application.

Note that Dispatch functions are all loaded into the global namespace.

## Configuration Variables

Certain properties and behaviours of Dispatch can be configured via the following `config()` entries:

```php
<?php
// load contents of ini file into config
config('source', 'inifile.ini');

// optional, specify your app's full URL (used by site_url())
config('site.url', 'http://somedomain.com/someapp/path');

// specify the routing file to be taken off of the request URI
// this is useful if you're on apache and don't have mod_rewrite
config('site.router', 'index.php');

// specify where to find your views
config('views.root', '../views');

// specify your default layout file (found within views)
config('views.layout', 'layout');

// salt to use for encrypting your cookies
config('cookies.secret', 'some-secret-token');

// cookie name to use for flash messages
config('cookies.flash', '_F');
?>
```

## URL Routing
Dispatch supports the `GET`, `POST`, `PUT`, `DELETE`, and `HEAD` request methods. To create routes for these methods, you can either use their equivalent convenience functions or call `route()` directly.

```php
<?php
// get route for index
get('/index', function () {
  echo "hello, world!\n";
});

// this is the same as above
route('GET', '/index', function () {
  echo "hello, world!\n";
});

// other routing functions are
// post()
// put()
// delete()
// head()
```

## URL Rewriting and Stripping
Setting `site.router` to a string will strip that string from the URI before it is routed. Two use cases for this are when you don't have access to URI rewriting on your server, and if your dispatch application resides in a subdirectory.

```php
<?php
// example 1: want to strip the index.php part from the URI
config('site.router', 'index.php');

get('/users', function () {
  echo "listing users...";
});

// requested URI = /index.php/users
// response = "listing users..."

// example 2: our app lives in /mysite
config('site.url', 'http://somehost.com/mysite');

// example 2.1: our routing file is at /mysite/index.php
config('site.router', 'index.php');

get('/users', function () {
  echo "listing users...";
});

// requested URI = http://somehost.com/mysite/index.php/users
// response = "listing users..."
?>
```

## Site URL and Site Path
In your app, you usually have a need to get your site's domain and your application's entire path. This can be setup by assigning a value to `site.url` in your config. Doing this will let you fetch its parts by calling `site_url($path_only = false)`.

```php
<?php
// map entire app path
config('site.url', 'http://somedomain.com/myapp');

// get the entire url
$complete = site_url();

// get just the app path
$path = site_url($path_only = true);
?>
```

## RESTful Objects
If you have a class that supports all or some of the default REST actions, you can easily publish them using `restify()`. By default, `restify()` will create all REST routes for your class. You can selectively publish actions by passing them to the function. To make a class support `restify()`, you need to implement some or all of the following methods:

* `onIndex` - for the resource list
* `onNew` - for the resource creation form
* `onCreate` - for the creation action
* `onShow($id)` - for viewing a resource
* `onEdit($id)` - for the resource edit form
* `onUpdate($id)` - for the resource edit action
* `onDelete($id)` - for the resource delete action

Note that the routes published by `restify()` uses the symbol `:id` to identify the resource.

```php
// resource to publish
class Users {
  public function onIndex() {}
  public function onNew() {}
  public function onCreate() {}
  public function onShow($id) {}
  public function onEdit($id) {}
  public function onUpdate($id) {}
  public function onDelete($id) {}
}

// publish the instance, with all endpoints, under /users
restify('/users', new Users());

// resource with just some of the REST endpoints
class Pages {
  public function onIndex() {
    echo "Pages::onIndex\n";
  }
  public function onShow($id) {
    echo "Pages::onShow {$id}\n";
  }
}

// publish object under /pages, but with just the available actions
restify('/pages', new Pages(), array('index', 'show'));
?>
```

## DELETE and PUT Request Overrides
Until browsers provide support for DELETE and PUT methods in their forms, you can instead use a `hidden` `input` field named `\_method` to override the request method for your form.

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

## PUT Requests and JSON Requests
In cases where you're handling PUT requests or JSON posts and you need access to the raw http request body contents, you can use `request_body()` for this. The `request_body()` function will return an associative array containing the body's `content-type`, `content-length`, `content-parsed` and `content-raw`.

The `request_body()` function accepts an optional parameter, which should be a `callable` that takes three arguments - content type, length and raw data. This callable will be treated as the parser for the data and whatever it returns will be used by `request_body()` as the value for `content-parsed`.

```php
put('/users/:id', function ($id) {
  $data = request_body();
  // or
  $data = request_body(function ($type, $length, $raw) {
    return json_decode($raw);
  });
  /**
  $data will be a hash of this structure
  array(
    'content-type' => 'content/type-here',
    'content-length' => 123,
    'content-parsed' => 'some data',
    'content-raw' => 'raw data'
  )
  */
});
```

## Route Symbol Filters
This is taken from ExpressJS. Route filters let you map functions against symbols in your routes. These functions then get executed when those symbols are matched.

```php
<?php
// preload blog entry whenever a matching route has :blog_id in it
filter('blog_id', function ($blog_id) {
	$blog = Blog::findOne($blog_id);
	// stash() lets you store stuff for later use (NOT a cache)
	stash('blog', $blog);
});

// here, we have :blog_id in the route, so our preloader gets run
get('/blogs/:blog_id', function ($blog_id) {
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
  // do something...
  // maybe setup the DB?
});

// setup a function to be called after each request
after(function () {
  // clean up stuff
  // close connections
  // etc
});
```

## Views and Partials
Dispatch gives you two functions for displaying views or templates and for loading view segments or partials - `render()` and `partial()`. When you call these functions, Dispatch looks for the filenames you pass to it inside the path you set `views.root` to. The view files need to have the `.html.php` extensions. For partials, the filenames need to begin with the underscore (_).

```php
<?php
// this echos the contents of the templates, using the values
// passed to it as locals in the template's scope
render('users/profile', array('name' => 'jaydee', 'age' => 35));

// by default, render uses a file called 'layout.html.php' as it's layout file.
// to use a different one, pass the filename as the third argument (minues the extension)
render('users/profile', null, 'custom_layout');

// if you're trying to dump jason data, you can skip a layout file by setting it to false
render('users/profile.json', null, false);

// partial files have filenames prefixed with the underscore (_). you don't need to
// put in the underscore when loading them
$html = partial('users/profile_links', array('data' => $data));
```

## $\_GET and $\_POST Values
If you want to fetch a value from a request without regard to wether it comes from `$_GET` or `$_POST`, you can use the function `param($name)` to get this value. This is just like Rails' `params` hash, where the `$_POST` values take priority over the `$_GET` values.

```php
<?php
// get 'name' from either $_GET or $_POST
$name = param('name');

// get 'name', set a default value if not found
$name = param('name', 'stranger');
?>
```

## $\_FILES Values
Dispatch gives you the function `upload($name)` to fetch the information on a file upload field from the `$_FILES` superglobal. If the field is present, then the function will return a hash containing all file information about the upload. This function also works on array of files. Every file that passes through `upload($name)` is checked with `is_uploaded_file()`.

```php
<?php
// get info on an uploaded file
$file = upload('photo');
?>
```

## Configurations
You can make use of ini files for configuration by doing something like `config('source', 'myconfig.ini')`.
This lets you put configuration settings in ini files instead of making `config()` calls in your code.

```php
<?php
// load the contents of my-settings.ini into config()
config('source', 'my-settings.ini');

// set a different folder for the views
config('views.root', __DIR__.'/myviews');

// get the encryption secret
$secret = config('cookies.secret');
?>
```

## Caching with APC and Memcached
If you have either APC or Memcached loaded into PHP, you can enable the caching functions
by making a call to `cache_enable()` and passing either `apc` or `memcached` to specify
which backend library to use.

Using the cache lets you then make calls to `cache()` and `cache_invalidate()`.

```php
<?php
// fetch something from the cache (ttl param is 60)
$data = cache('users', function () {
  // this function is called as a loader if apc
  // doesn't have 'users' in the cache, whatever
  // it returns gets stored into apc and mapped to
  // the 'users' key
  return array('sheryl', 'addie', 'jaydee');
}, 60);

// invalidate our cached keys (users, products, news)
cache_invalidate('users', 'products', 'news');
```

Note that if you're using `memcached` as cache, you also need to define the connection
string to be used via `config('cache.connection')`. This setting can be a single string
or an array of strings, with the format `hostname:port:weight`.

```ini
; for a single server
cache.connection = localhost:11211

; for multiple servers
cache.connection[] = hostname1:11211:40
cache.connection[] = hostname2:11211:40
cache.connection[] = hostname3:11211:20
```

## Utility Functions
There are a lot of other useful routines in the library. Documentation is still lacking but they're very small and easy to figure out. Read the source for now.

```php
<?php
// store a config and get it
config('views.root', './views');
config('views.root'); // returns './views'

// stash a var and get it (useful for moving stuff between scopes)
stash('user', $user);
stash('user'); // returns stored $user var

// redirect with a status code
redirect(302, '/index');

// redirect if a condition is met
redirect(403, '/users', !$authenticated);

// redirect only if func is satisfied
redirect('/admin', function () use ($auth) { return !!$auth; });

// redirect only if func is satisfied, and with a diff code
redirect(301, '/admin', function () use ($auth) { return !!$auth; });

// send a http error code and print out a message
error(403, 'Forbidden');

// get the current HTTP method or check the current method
method(); // GET, POST, PUT, DELETE
method('POST'); // true if POST request, false otherwise

// client's IP
client_ip();

// get a value from $_POST, returns null if not set
$name = from($_POST, 'name');

// create an associative array using the passed keys,
// pulling the values from $_POST
$user = from($_POST, array('username', 'email', 'password'));

// try to get a value from $_GET, use a default value if not set
$user = from($_GET, 'username', 'Sranger');

// set a flash message
flash('error', 'Invalid username');

// in a subsequent request, get the flash message
$error = flash('error');

// escape a string
_h('Marley & Me');

// url encode
_u('http://noodlehaus.github.com/dispatch');

// load a partial using some file and locals
$html = partial('users/profile', array('user' => $user));
?>
```

## Related Libraries
* [disptach-mongo](http://github.com/noodlehaus/dispatch-mongo) - wrapper for commonly used mongodb functions for dispatch
* [disptach-elastic](http://github.com/noodlehaus/dispatch-elastic) - wrapper for commonly used elasticsearch operations for dispatch
* [runphp](http://noodlehaus.github.io/runphp) - a PHP RESTful API library, and some

## About the Author

Dispatch is written by me, [Jesus A. Domingo]. If you think this library offers some things you don't need and you just want the
routing-related stuff, you might want to check out [RunPHP] instead.

[Jesus A. Domingo]: http://github.com/noodlehaus
[RunPHP]: http://noodlehaus.github.io/runphp

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
