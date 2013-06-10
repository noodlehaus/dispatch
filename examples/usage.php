<?php
error_reporting(E_ALL|E_STRICT);

include '../src/dispatch.php';

before(function () {
  echo "inside callback mapped through before()\n";
});

after(function () {
  echo "inside callback mapped through after()\n";
});

// some sample routes
get('/index', function () {
  echo "GET index\n";
});

post('/index', function () {
  echo "POST index\n";
});

put('/index', function () {
  echo "PUT index\n";
});

delete('/index', function () {
  echo "DELETE index\n";
});

// let's call those routes
dispatch('GET', '/index');
dispatch('POST', '/index');
dispatch('PUT', '/index');
dispatch('DELETE', '/index');

// a sample class that will be published
// as a RESTful resource with all actions
class Users {
  public function onIndex() {
    echo "Users::onIndex\n";
  }
  public function onNew() {
    echo "Users::onNew\n";
  }
  public function onCreate() {
    echo "Users::onCreate\n";
  }
  public function onShow($id) {
    echo "Users::onShow {$id}\n";
  }
  public function onEdit($id) {
    echo "Users::onEdit {$id}\n";
  }
  public function onUpdate($id) {
    echo "Users::onUpdate {$id}\n";
  }
  public function onDelete($id) {
    echo "Users::onDelete {$id}\n";
  }
}

// a sample class that will be published
// as a RESTful resource with just 2 actions
class Pages {
  public function onIndex() {
    echo "Pages::onIndex\n";
  }
  public function onShow($id) {
    echo "Pages::onShow {$id}\n";
  }
}

// some route filters, gets called when a matching
// route contains the given symbol (in this case, :id)
filter('id', function ($id) {
  echo "caught {$id} as :id\n";
});

filter('id', function ($id) {
  echo "also caught {$id} as :id\n";
});

// publish instances of the two classes
// as RESTful resources
restify('/users', new Users());

// another resource, but with just two actions
restify('/pages', new Pages(), array('index', 'show'));

// let's call those REST endpoints
dispatch('GET', '/users/index');
dispatch('GET', '/users/new');
dispatch('POST', '/users/create');
dispatch('GET', '/users/1');
dispatch('GET', '/users/1/edit');
dispatch('PUT', '/users/1');
dispatch('DELETE', '/users/1');

// this resource only supports two actions,
// other actions will return 404s
dispatch('GET', '/pages/');
dispatch('GET', '/pages/1');

// simulate no mod_rewrite and subdir apps
config('site.router', 'mysite/index.php');

// index.php/ will be stripped from the request URIs
dispatch('GET', '/mysite/index.php/users');

// some custom 404 error hooks
error(404, function () {
  echo "Nyargh!\n";
});

// you can have multiple error handlers
error(404, function () {
  echo "Oops!\n";
});

// try out new routing
route('GET /sample-route', function () {
  echo "new route format\n";
});

// call new route
dispatch('GET', '/sample-route');
?>
