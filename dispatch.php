<?php

# @license MIT

# returns by ref the route stack singleton
function &context() {
  static $context = [];
  return $context;
}

# dispatch sapi request against routes context
function dispatch(...$args) {

  $verb = strtoupper($_SERVER['REQUEST_METHOD']);
  $path = '/'.trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

  # post method override
  if ($verb === 'POST') {
    if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
      $verb = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    } else {
      $verb = isset($_POST['_method']) ? strtoupper($_POST['_method']) : $verb;
    }
  }

  $resp = pico_serve(context(), $verb, $path, ...$args);
  pico_render(...$resp);
}

# creates an page-rendering action
function page($path, array $vars = []) {
  return function () use ($path, $vars) {
    return pico_response(phtml($path, $vars));
  };
}

# renders and returns the content of a template
function phtml($path, array $vars = []) {
  ob_start();
  extract($vars, EXTR_SKIP);
  require "{$path}.phtml";
  return trim(ob_get_clean());
}

# creates redirect response
function redirect($location, $status = 302) {
  return pico_response('', $status, ['location' => $location]);
}

# creates an action and puts it into the routes stack
function route($verb, $path, callable $func) {
  $context = &context();
  array_push($context, pico_action($verb, $path, $func));
}

# forwarders to pico

function action($verb, $path, callable $func) {
  return pico_action($verb, $path, $func);
}

function match(array $actions, $verb, $path) {
  return pico_lookup($actions, $verb, $path);
}

function response($content, $status = 200, $headers = []) {
  return pico_response($content, $status, $headers);
}

function serve(array $actions, $verb, $path, ...$args) {
  return pico_serve($actions, $verb, $path, ...$args);
}

function render($content, $status = 200, $headers = []) {
  return pico_render($content, $status, $headers);
}
