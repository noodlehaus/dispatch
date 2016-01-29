## dispatch

- a tiny library for quick and easy PHP apps
- requires PHP 5.6+

Here's a sample of how you'd usually use `dispatch`.

```php
<?php

require 'path/to/dispatch.php';

# sample JSON end point
route('GET', '/books.json', function ($db, $config) {
  $list = loadAllBooks($db);
  $json = json_encode($list);
  return response($json, 200, ['content-type' => 'application/json']);
});

# html end point
route('GET', '/books/:id', function ($args, $db) {
  $book = loadBookById($db, $args['id']);
  $html = phtml(__DIR__.'/views/book', ['book' => $book]);
  return response($html);
});

# respond using a template
route('GET', '/about', page(__DIR__.'/views/about'));

# sample dependencies
$config = require __DIR__.'/config.php';
$db = createDBConnection($config['db']);

# arguments you pass here get forwarded to the route actions
dispatch($db, $config);
```

If you want to see where all the state goes, you can use `action`, `serve`,
and `render` instead.

```php
<?php

require 'path/to/dispatch.php';

# create a stack of actions
$routes = [
  action('GET', '/books.json', function ($db, $config) {
    $list = loadAllBooks($db);
    $json = json_encode($list);
    return response($json, 200, ['content-type' => 'application/json']);
  }),
  action('GET', '/books/:id', function ($args, $db) {
    $book = loadBookById($db, $args['id']);
    $html = phtml(__DIR__.'/views/book', ['book' => $book]);
    return response($html);
  }),
  action('GET', '/about', page(__DIR__.'/views/about'))
];

# sample dependencies
$config = require __DIR__.'/config.php';
$db = createDBConnection($config['db']);

# we need the method and requested path
$verb = $_SERVER['REQUEST_METHOD'],
$path = $_SERVER['REQUEST_URI'],

# serve app against verb + path, pass dependencies
list($content, $status, $headers) = serve($routes, $verb, $path, $db, $config);

# flush out content, status code, and http headers
render($content, $status, $headers);
```

## tests

Tests for `dispatch` use plain assertions.

```
php tests/dispatch-tests.php
```

If no errors were echoed, then you're all good.

## breaking changes from 9.x

```
noodlehaus/dispatch package name
```

## breaking changes from 8.x

```
removed the following:
- attachments()
- blanks()
- config()
- cookies()
- ent()
- error()
- headers()
- hook()
- input()
- ip()
- json()
- map()
- nocache()
- session()
- stash()
- status()
- url()

added the following:
- action() - creates a route handler
- response() - returns a response tuple
- match() - matches the method + pattern against a list of actions
- serve() - performs match() against actions list, and invokes the
  match, and returns the resulting response tuple
- page() - creates an action that renders the file, like inline phtml()
- render() - renders the http response
- route() - creates an action and puts it into the context singleton
  (replaces the old map function)
- context() - returns the context singleton

changed the following:
- redirect() - now just returns a redirect response tuple
- phtml() - now requires the full path (minus extension) of phtml file,
  and no longer accepts a layout file
```

## license

MIT
