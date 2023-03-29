<?php

# @author noodlehaus
# @license MIT

# returns by ref the route stack singleton
function &context() {
  static $context = [];
  return $context;
}

# dispatch sapi request against routes context
function dispatch(...$args) {

  $method = strtoupper($_SERVER['REQUEST_METHOD']);
  $path = '/'.trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

  # post method override
  if ($method === 'POST') {
    if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
      $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    } else {
      $method = isset($_POST['_method']) ? strtoupper($_POST['_method']) : $method;
    }
  }

  $responder = serve(context(), $method, $path, ...$args);
  $responder();
}

# creates an action and puts it into the routes stack
function route($method, $path, callable $handler) {
  $context = &context();
  array_push($context, action($method, $path, $handler));
}

# creates a route handler
function action($method, $path, callable $handler) {
  $regexp = '@^'.preg_replace('@:(\w+)@', '(?<\1>[^/]+)', $path).'$@';
  return [strtoupper($method), $regexp, $handler];
}

# creates standard response
function response($body, $code = 200, array $headers = []) {
  return function () use ($body, $code, $headers) {
    render($body, $code, $headers);
  };
}

# creates redirect response
function redirect($location, $code = 302) {
  return function () use ($location, $code) {
    render('', $code, ['location' => $location]);
  };
}

# dispatches method + path against route stack
function serve(array $routes, $method, $path, ...$args) {

  $method = strtoupper(trim($method));
  $path = '/'.trim(rawurldecode(parse_url($path, PHP_URL_PATH)), '/');

  $action = null;
  $params = null;

  # test method + path against action method + expression
  foreach ($routes as list($action_method, $regexp, $handler)) {
    if ($method === $action_method && preg_match($regexp, $path, $caps)) {
      $action = $handler;
      $params = array_slice($caps, 1);
      break;
    }
  }

  # no matching route, 404
  if (!$action) {
    return response('', 404, []);
  }

  return empty($params)
    ? $action(...$args)
    : $action($params, ...$args);
}

# renders request response to the output buffer (ref: zend-diactoros)
function render($body, $code = 200, $headers = []) {
  http_response_code($code);
  array_walk($headers, function ($value, $key) {
    if (! preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $key)) {
      throw new InvalidArgumentException("Invalid header name - {$key}");
    }
    $values = is_array($value) ? $value : [$value];
    foreach ($values as $val) {
      if (
        preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", $val) ||
        preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $val)
      ) {
        throw new InvalidArgumentException("Invalid header value - {$val}");
      }
    }
    header($key.': '.implode(',', $values));
  });
  print $body;
}

# creates an page-rendering action
function page($path, array $vars = []) {
  return function () use ($path, $vars) {
    return response(phtml($path, $vars));
  };
}

# renders and returns the content of a template
function phtml($path, array $vars = []) {
  ob_start();
  extract($vars, EXTR_SKIP);
  require "{$path}.phtml";
  return trim(ob_get_clean());
}
