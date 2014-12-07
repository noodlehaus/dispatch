# DISPATCH API

## table of contents

- [application entry point](#application-entry-point)
- [handlers](#handlers)
- [method overrides](#method-overrides)
- [error handlers](#error-handlers)
- [redirects](#redirects)
- [route parameters](#route-parameters)
- [route parameter hooks](#route-parameter-hooks)
- [page rendering](#page-rendering)
- [configuration files](#configuration-files)
- [headers, cookies, session, and uploads](#headers-cookies-session-and-uploads)
- [miscellaneous](#miscellaneous)
- [url rewriting](#url-rewriting)

### application entry point

```php
<?php
# serve request
dispatch();
```

Pass variables to your handlers by passing them as arguments to `dispatch()`.

```php
<?php
# map a handler that expects the db conn
map('GET', '/users/list', function ($db) {
  # ... do something with $db
});

# some resource, etc.
$db = new Connection();

# args you pass to dispatch() gets forwarded
dispatch($db);
```

### handlers

```php
<?php
# map handler against method(s) + route
map('POST', '/users/create', function () {});
map(['HEAD', 'GET', 'OPTIONS'], '/users/{id}', function () {});

# map handler against a route(s)
map('/about', function () {});
map(['/about', '/contact'], function () {});

# map handler against everything
map(function () {});
```

### method overrides

For `POST` requests, If you have `x-http-method-override` set, that will be
used. If not, it checks for `$_POST['_method']` and uses that if found.

### error handlers

```php
<?php
# map handler against error codes, first argument is the error code
map(404, function ($code) {});
map([400, 401, 403, 404], function ($code) {});

# trigger an error handler for the code
error(404);
```

Pass arguments to your error handler by appending them to the
`error()` arguments.

```php
<?php
# expect a resource (2nd argument) in your error handler
map(404, function ($code, $res) {
  # ... do something with $res
});

# trigger an error handler while passing an argument
error(404, $some_resource);
```

### redirects

```php
<?php
# default, redirect with 302 and don't end execution
return redirect('/new-location');

# redirect with a specific code, and terminate
return redirect('/new-location', 301, $halt = true);
```

### route parameters

```php
<?php
# if you have route symbols, a hash of their values will be passed first
map('GET', '/users/{id}', function ($params) {
  $id = $params['id'];
});

# if you have args from dispatch(), they will come after the params hash
map('GET', '/topics/{id}', function ($params, $db) {
  $id = $params['id'];
});

# pass an argument during dispatch
dispatch($db);
```

### route parameter hooks

```php
<?php
# if {id} is in the matching route, replace it
hook('id', function ($id) {
  return [$id, md5($id)];
});

# handler can expect modified data
map('GET', '/users/{id}', function ($params) {
  list($id, $id_md5) = $params['id'];
});
```

### page rendering

```php
# render partial file hello.phtml
$partial = phtml(__DIR__.'/views/hello', ['name' => 'stranger'], false);

# render hello.phtml inside layout layout.phtml
echo phtml(
  __DIR__.'/views/hello',
  ['name' => 'stranger'],
  __DIR__.'/views/layout'
);
```

Put `templates = <views dir>` in a config file and load it via `config()`
to tell dispatch where to find your templates.

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

### configuration files

```ini
some_setting_1 = yes
some_setting_2 = foo
```

```php
<?php
config(parse_ini_file('config.ini'));

$some_setting_1 = config('some_setting_1');
$some_setting_2 = config('some_setting_2');
```

The following configuration entries change how some of Dispatch's behavior.

```ini
; your applications base URL
url = http://localhost/myapp

; if you don't have access to URL rewrites (this gets stripped)
router = index.php

; tell dispatch where to find phtml files
templates = ./views
```

### headers, cookies, session, and uploads

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

### miscellaneous

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

# get what foo() set
function bar() {
  $name = stash('name');
}
```

### url rewriting

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

## LICENSE

MIT <http://noodlehaus.mit-license.org>
