<?php declare(strict_types=1);

# @author noodlehaus
# @license MIT

# returns by ref the route stack singleton
function &context(): array {
  static $context = [];
  return $context;
}

# dispatch sapi request against routes context
function dispatch(...$args): void {

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
function route(string $method, string $path, callable $handler): void {
  $context = &context();
  array_push($context, action($method, $path, $handler));
}

# creates a route handler
function action(string $method, string $path, callable $handler): array {
  $regexp = '@^'.preg_replace('@:(\w+)@', '(?<\1>[^/]+)', $path).'$@';
  return [strtoupper($method), $regexp, $handler];
}

# creates standard response
function response(string $body, int $code = 200, array $headers = []): callable {
  return fn() => render($body, $code, $headers);
}

# creates redirect response
function redirect(string $location, int $code = 302): callable {
  return fn() => render('', $code, ['location' => $location]);
}

# dispatches method + path against route stack
function serve(array $routes, string $method, string $path, ...$args): callable {

  $method = strtoupper(trim($method));
  $path = '/'.trim(rawurldecode(parse_url($path, PHP_URL_PATH)), '/');

  $action = null;
  $params = null;

  # test method + path against action method + expression
  foreach ($routes as [$action_method, $regexp, $handler]) {
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

  # invoke matching handler
  return empty($params)
    ? $action(...$args)
    : $action($params, ...$args);
}

# renders request response to the output buffer (ref: zend-diactoros)
function render(string $body, int $code = 200, $headers = []): void {
  http_response_code($code);
  array_walk($headers, function ($value, $key) {
    if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $key)) {
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
function page(string $path, array $vars = []): callable {
  return fn() => response(phtml($path, $vars));
}

# renders and returns the content of a template
function phtml(string $path, array $vars = []): string {
  ob_start();
  extract($vars, EXTR_SKIP);
  require "{$path}.phtml";
  return trim(ob_get_clean());
}
