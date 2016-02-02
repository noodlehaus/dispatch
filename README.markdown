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

If you want to see where all the state goes, you can use `action` and `serve`.

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
$responder = serve($routes, $verb, $path, $db, $config);

# invoke responder to flush response
$responder();
```

## tests

Tests for `dispatch` use plain assertions.

```
php tests/dispatch-tests.php
```

If no errors were printed, then you're all good.

## license

MIT
