## Dispatch PHP Microframework
At the very least, *dispatch* is a front controller for your web application. It lets you define routes in your application, organize your code into controllers and views, along with some other functions useful in creating web apps.

## Basic Example
``` php
include './lib/dispatch.php';

config('views', './views');
config('layout', 'layout');

get('/greet', function () {
	render('greet-form');
});

post('/greet', function () {
	$name = from($_POST, 'name');
	render('greet-show', array('name' => $name));
});

dispatch();
```

## Route Symbol Preloader
This is a port of ExpressJS' route preconditions.

``` php
include './lib/dispatch.php';

preload('blog_id', function ($blog_id) {
	$blog = Blog::findOne($blog_id);
	stash('blog', $blog);
});

get('/blogs/:blog_id', function ($blog_id) {
	// pick up what we got from the stash
	$blog = stash('blog');
	render('blogs/show', array('blog' => $blog);
});

dispatch();
```

## Execution Preconditions
This is taken from BreezePHP.

``` php
include './lib/dispatch.php';

precondition('token_valid', function ($token) {
	if ($token !== md5('s3cr3t-s4uc3'.client_ip())) {
		error('Unauthorized!', 403);
	}
});

get('/admin', function () {
	precondition('token_valid');
	render('admin');
});

dispatch();
```

## Route Pass Through
By default, dispatch will only execute the first route handler that matches the request URI. To let the route matching continue, call *pass()*.

``` php
include './lib/dispatch.php';

get('/blog/:slug', function ($slug) {
	if ($slug == 'admin') {
		pass();
	}
	$blog = Blog::findBySlug($slug);
	render('blogs/show', array('blog' => $blog));
});

get('/blog/admin', function () {
	render('admin');
});

dispatch();
```

## LICENSE
(The MIT License)

Copyright (c) 2011 Jesus A. Domingo jesus.domingo@gmail.com

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the 'Software'), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
