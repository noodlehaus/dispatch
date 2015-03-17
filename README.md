# DISPATCH API

## Breaking Changes in 8.0.0

- Package name changed to `badphp/dispatch`

## Breaking Changes in 7.0.0

- Route parameter delimeters have been changed to `<>`, from `{}`

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Application Entry Point](#application-entry-point)
- [Handlers](#handlers)
- [Error Handlers](#error-handlers)
- [Redirects](#redirects)
- [Route Parameters](#route-parameters)
- [Route Parameter Hooks](#route-parameter-hooks)
- [PHP Templates](#php-templates)
- [Configurations](#configurations)
- [Headers, Cookies, Session, Uploads](#headers-cookies-session-uploads)
- [Miscellaneious](#miscellaneous)
- [URL Rewriting](#url-rewriting)

### Requirements

Dispatch requires **PHP 5.4+** to run.

### Installation

Download and include the file `dispatch.php` directly or install it using
composer:

```sh
$ composer require badphp/dispatch
```

### Application Entry Point

Request handling and route matching starts after you call `dispatch()`.

```php
# serve request
dispatch();
```

You can pass variables to your handlers by passing them as arguments to `dispatch()`.

```php
# map a handler that expects the db conn
map('GET', '/users/list', function ($db) {
  # ... do something with $db
});

# some resource, etc.
$db = new Connection();

# args you pass to dispatch() gets forwarded
dispatch($db);
```

### Handlers

Route handlers are mapped using the `map()` function.

```php
# map handler against method(s) + route
map('POST', '/users/create', function () {});
map(['HEAD', 'GET', 'OPTIONS'], '/users/<id>', function () {});

# map handler against a route(s) + any method
map('/about', function () {});
map(['/about', '/contact'], function () {});

# map handler against everything
map(function () {});
```

For `POST` requests, If you have `x-http-method-override` set, that will be
used. If not, it checks for `$_POST['_method']` and uses that if found.

### Route Parameters

Routes can have parameter values. When specified, their matching values
are passed into your handler via a hash.

```php
# if you have route symbols, a hash of their values will be passed first
map('GET', '/users/<id>', function ($params) {
  $id = $params['id'];
});

# you can attach regex rules to your route symbols as well
map('GET', '/users/<id:\d{2,5}>', function ($params) {
  # {id} will match 12, but not 1, or 123456
});

# if you have args from dispatch(), they will come after the params hash
map('GET', '/topics/<id>', function ($params, $db) {
  $id = $params['id'];
});

# pass an argument during dispatch
dispatch($db);
```

### Route Parameter Hooks

In some cases, we want to preload some data associated with a route
parameter value. We can do this with route parameter hooks via `hook()`.

```php
# if <id> is in the matching route, replace it
hook('id', function ($id) {
  return [$id, md5($id)];
});

# handler can expect modified data
map('GET', '/users/<id>', function ($params) {
  list($id, $id_md5) = $params['id'];
});
```

### Error Handlers

HTTP error handlers are mapped using the same `map()` function, but instead
of the usual method + route + action arguments, you just pass the http code
and action.

```php
# map handler against error codes, first argument is the error code
map(404, function ($code) {});
map([400, 401, 403, 404], function ($code) {});
```

You can then trigger the HTTP error handler with `error()`.

```php
# trigger an error handler for the code
error(404);
```

You can also pass arguments to your error handler by passing them along in
your `error()` call.

```php
# expect a resource (2nd argument) in your error handler
map(404, function ($code, $res) {
  # ... do something with $res
});

# trigger an error handler while passing an argument
error(404, $some_resource);
```

### Redirects

Page redirects are done with `redirect()`, which can also finish script
execution if `$halt` is `true`.

```php
# default, redirect with 302 and don't end execution
return redirect('/new-location');

# redirect with a specific code, and terminate
return redirect('/new-location', 301, $halt = true);
```

### PHP Templates

Render PHP templates using the `phtml()` function. This function assumes
your that your templates end in `.phtml`.

```php
# render partial file hello.phtml
$partial = phtml(__DIR__.'/views/hello', ['name' => 'stranger'], false);
```

To use layout templates, provide a section in your template that prints
out the contents of `$body`.

```php
<!-- views/layout.phtml -->
<body><?php echo $body ?></body>
```

```php
# render hello.phtml inside layout.phtml
echo phtml(
  __DIR__.'/views/hello',
  ['name' => 'stranger'],
  __DIR__.'/views/layout'
);
```

For convenience, you can Tell dispatch where to find templates by setting
the `templates` setting via `config()`.

```ini
; config.ini
templates = ./views
```

```php
<?php
# load config
config(parse_ini_file('config.ini'));

# get a partial
$partial = phtml('hello', ['name' => 'stranger'], false);

# render page
echo phtml('hello', ['name' => 'stranger'], 'layout');
```

### Configurations

Load, set and access configuration settings via the `config()` function.
Pass a hash to `config()` to set multiple settings at once, a string
to fetch the configuration value for that key, or a string and a value,
to set the value for that configuration key.

```ini
; config.ini
some_setting_1 = yes
some_setting_2 = foo
```

We then load the values in the following way:

```php
<?php
# initialize the config container
config(parse_ini_file('config.ini'));

# set a config value
config('my_custom_config', 'foobar');

# fetch some values
$some_setting_1 = config('some_setting_1');
$some_setting_2 = config('some_setting_2');
```

The following configuration entries change some of Dispatch's behavior.

```ini
; your applications base URL
url = http://localhost/myapp

; if you don't have access to URL rewrites (this gets stripped)
router = index.php

; tell dispatch where to find phtml files
templates = ./views
```

### Headers, Cookies, Session, and Uploads

Get, set headers and cookies via `headers()` and `cookies()`, respectively.
To access file upload information, use `attachment()`.

```php
<?php
# GET a REQUEST header
$token = headers('x-http-token');

# SET a RESPONSE header
headers('x-http-token', 's3cr3t');

# GET a REQUEST cookie value
$name = cookies('name');

# SET a RESPONSE cookie (maps directly to setcookie)
cookies('name', $name, time() + 3500, '/');

# GET a session var
$name = session('name');

# SET a session var
session('name', $name);

# get info about an uploaded file
$upload = attachment('photo');

# get the raw request body as a file
$info = input();
# $info[0] - content type
# $info[1] - path to file containing request body

# get the raw request body
$info = input($load = true);
# $info[0] - content type
# $info[1] - content body (watch out for big uploads)
```

### Miscellaneous

Other frequently used functionalities wrapped for convenience.

```php
<?php
# get client IP
$ip = ip();

# htmlentities() alias
$html = ent($str);

# urlencode alias
$link = url($str);

# print out no-cache headers
nocache();

# stream back json headers + data
json($some_data);

# set response status
status(200);

# get a hash of empty fields (for forms)
$user = blanks('username', 'email', 'location');

# set a cross-scope value
function foo() {
  stash('name', 'foo');
}

# get what foo() stored
function bar() {
  $name = stash('name');
}
```

### URL Rewriting

Dispatch relies on URL rewrites being enabled. Here are some configurations
for seting them up on Apache and Nginx.

```
# apache
<IfModule mod_rewrite.c>
  RewriteEngine on
  # in this case, our app bootstrap file is index.php
  RewriteRule !\.(js|html|ico|gif|jpg|png|css)$ index.php
</IfModule>
```

```
# nginx
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

## CONTRIBUTORS

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
* Bryan Haskin [bhhaskin](https://github.com/bhhaskin)
* Olav Schettler [oschettler](https://github.com/oschettler)

## LICENSE

MIT <http://noodlehaus.mit-license.org>
