## Dispatch PHP 5.3 Utility Library
At the very least, `dispatch()` is a front controller for your web app. It doesn't give you the full MVC setup, but it lets you define url routes and segregate your app logic from your views.

### Requirements
* PHP 5.3
* `mcrypt` extension if you want to use encrypted keys and wish to use `encrypt()` and `decrypt()` functions

### Configuration Variables
The following functions rely on variables set via `config()`:
* `config('views')` is used by `render()` and `partial()`, defaults to `./views`
* `config('layout')` is used by `render()`, defaults to `layout`
* `config('secret')` is used by `encrypt()`, `decrypt()`, `set_cookie()` and `get_cookie()`, defaults to an empty string
* `config('expire')` is used by `set_cookie()` and `get_cookie()`, defaults to `31536000`
* `config('rewrite')` is used by `dispatch()` to compensate for the lack of `mod_rewrite`, defaults to `true`
* `config('source')` makes the specified ini contents accessible via `config()` calls

### Quick and Basic
A typical PHP app using dispatch() will look like this.

```php
<?php
// include the library
include 'dispatch.php';

// define your routes
get('/greet', function () {
	// render a view
	render('greet-form');
});

// post handler
post('/greet', function () {
	$name = from($_POST, 'name');
	// render a view while passing some locals
	render('greet-show', array('name' => $name));
});

// serve your site
dispatch();
?>
```

### Route Symbol Filters
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

### Middleware
If you have wind up routines that need to be done before handling the request, you can queue them up using the `middleware()` function.

```php
<?php
// create a db connection and stash it
middleware(function () {
	$db = create_connection();
	stash('db', $db);
});

// assume that the db connection was stash()ed
get('/list', function () {
	$db = stash('db');
	// do stuff with the DB
});
?>
```

### Route Pass Through
This is also taken from BreezePHP. By default, dispatch will only execute the first route handler that matches the request URI. To let the route matching continue, call `pass()`.

```php
<?php
get('/blog/:slug', function ($slug) {
	// if the blog admin is what's being requested, let it through
	if ($slug == 'admin') {
		pass();
	}
	$blog = Blog::findBySlug($slug);
	render('blogs/show', array('blog' => $blog));
});

// this is our actual route handler
get('/blog/admin', function () {
	render('admin');
});
?>
```

### Configurations
You can make use of ini files for configuration by doing something like `config('source', 'myconfig.ini')`.
This lets you put configuration settings in ini files instead of making `config()` calls in your code.

```php
<?php
// load a config.ini file
config('source', 'my-settings.ini');

// set a different folder for the views
config('views', __DIR__.'/myviews');

// get the encryption secret
$secret = config('secret');
?>
```

### Utility Functions
There are a lot of other useful routines in the library. Documentation is still lacking but they're very small and easy to figure out. Read the source for now.

```php
<?php
// store a config and get it
config('views', './views');
config('views'); // returns './views'

// stash a var and get it (useful for moving stuff between scopes)
stash('user', $user);
stash('user'); // returns stored $user var

// redirect with a status code
redirect('/index', 302);

// redirect if a condition is met
redirect_if(!$authenticated, '/users', 302);

// send a http error code and print out a message
error(403, 'Forbidden');

// get the current HTTP method or check the current method
method(); // GET, POST, PUT, DELETE
method('POST'); // true if POST request, false otherwise

// client's IP
client_ip();

// get something or a hash from a hash
$name = from($_POST, 'name');
$user = from($_POST, array('username', 'email', 'password'));

// escape a string
html('Marley & Me');

// load a partial using some file and locals
$html = partial('users/profile', array('user' => $user));
?>
```

## LICENSE
(The MIT License)

Copyright (c) 2011 Jesus A. Domingo jesus.domingo@gmail.com

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the 'Software'), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
