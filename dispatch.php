<?php
# @author Jesus A. Domingo
# @license MIT <http://noodlehaus.mit-license.org>

# config settings
function config($name, $info = null) {

  $data = &$GLOBALS['noodlehaus\dispatch']['config'];

  if (func_num_args() === 1) {

    # value fetch
    if (is_string($name)) {
      return isset($data[$name]) ? $data[$name] : null;
    }

    # multi-key/nested value setting
    if (is_array($name)) {

      $k = array_keys($name);
      $n = count($k);

      if (count(array_filter($k, 'is_string')) == $n) {
        $data = array_replace_recursive($data, $name);
        return;
      }

      throw new InvalidArgumentException(
        "Associative array expected as first argument",
        500
      );
    }
  }

  return ($data[$name] = $info);
}

# wrapper for htmlentities()
function ent() {
  return call_user_func_array('htmlentities', func_get_args());
}

# wrapper for urlencode()
function url($str) {
  return urlencode($str);
}

# php template loader
function phtml($__n, $__v = [], $__l = 'layout') {

  # if we have templates set, use it as view base path
  if (($__d = config('templates')) !== null) {
    $__n = "{$__d}/{$__n}";
  }

  # extract locals (__v), require template (__n)
  extract($__v, EXTR_SKIP);
  ob_start();
  require "{$__n}.phtml";
  $__b = ob_get_clean();

  # if we have a layout file, render it
  if (!empty($__l) && is_string($__l)) {
    $__b = phtml($__l, ['body' => $__b] + $__v, null);
  }

  return $__b;
}

# returns hash containing args as keys, mapped to '' as values
function blanks() {
  return array_fill_keys(func_get_args(), '');
}

# returns the best-guess remote address
function ip() {

  if (isset($_SERVER['HTTP_CLIENT_IP'])) {
    return $_SERVER['HTTP_CLIENT_IP'];
  }

  if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return $_SERVER['HTTP_X_FORWARDED_FOR'];
  }

  return $_SERVER['REMOTE_ADDR'];
}

# in-request store for values
function stash($name = null, $value = null) {

  $data = &$GLOBALS['noodlehaus\dispatch']['stash'];
  $argc = func_num_args();

  # value fetch
  if ($argc === 1) {
    return isset($data[$name]) ? $data[$name] : null;
  }

  # stash reset
  if ($argc === 0) {
    return ($data = []);
  }

  # value assignment
  return ($data[$name] = $value);
}

# returns the value for an http request header, or sets an http
# response header (maps to php's header function)
function headers() {

  $headers = &$GLOBALS['noodlehaus\dispatch']['request_headers'];

  $argc = func_num_args();
  $argv = func_get_args();

  # error case
  if ($argc < 1) {
    throw new BadFunctionCallException(
      'Invalid number of arguments.',
      500
    );
  }

  # fetch case
  if ($argc === 1) {

    # first call, prime it
    if (!count($headers)) {

      # if we're not in CLI, use it
      if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders());
      } else {

        # manual header extraction (CLI + test)
        $special = ['CONTENT_LENGTH', 'CONTENT_MD5', 'CONTENT_TYPE'];

        # get the rest of the headers out
        foreach ($_SERVER as $name => $data) {
          if (0 === strpos($name, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($name, 5)));
            $headers[$name] = $data;
          } else if (in_array($name, $special)) {
            $name = strtolower(str_replace('_', '-', $name));
            $headers[$name] = $data;
          }
        }
      }
    }

    # normalize the input then try to fetch
    $name = strtolower(array_shift($argv));

    return isset($headers[$name]) ? $headers[$name] : null;
  }

  # remove first item, replace new head with name + value
  $argv[0] = array_shift($argv).': '.$argv[0];

  # header set call, forward it to header()
  return call_user_func_array('header', $argv);
}

# accessor for $_COOKIE when fetching values, or maps directly
# to setcookie() when setting values.
function cookies() {

  $argc = func_num_args();
  $argv = func_get_args();

  # cookie fetch, get from $_COOKIE, or null
  if ($argc == 1) {
    return isset($_COOKIE[$argv[0]]) ? $_COOKIE[$argv[0]] : null;
  }

  # set, just map to setcookie()
  return call_user_func_array('setcookie', $argv);
}

# accessor for $_SESSION
function session($name, $value = null) {

  # session var set
  if (func_num_args() == 2) {
    return ($_SESSION[$name] = $value);
  }

  # session var get
  return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
}

# accessor for $_FILES, also consolidates array file uploads, but when using
# the files, be sure to use either is_uploaded_file() or move_uploaded_file()
# to ensure validity of file targets
function attachments($name) {

  static $cache = [];

  # return cached copy
  if (isset($cache[$name])) {
    return $cache[$name];
  }

  if (!isset($_FILES[$name])) {
    return null;
  }

  # single-file attachment (no need to cache)
  if (!is_array($_FILES[$name]['name'])) {
    return $_FILES[$name];
  }

  # attachment is an array
  $result = [];

  # consolidate file info
  foreach ($_FILES[$name] as $k1 => $v1)
    foreach ($v1 as $k2 => $v2)
      $result[$k2][$k1] = $v2;

  # cache and return array uploads
  return ($cache[$name] = $result);
}

# read in raw request body
function input($load = false, $pipe = 'php://input') {

  static $cache = null;

  # if called before, just return previous data
  if ($cache) {
    return $cache;
  }

  # do a best guess
  $content_type = (
    isset($_SERVER['HTTP_CONTENT_TYPE']) ?
    $_SERVER['HTTP_CONTENT_TYPE'] :
    $_SERVER['CONTENT_TYPE']
  );

  # try to load everything
  if ($load) {

    $content = file_get_contents($pipe);
    $content_type = preg_split('/ ?; ?/', $content_type);

    # type-content tuple
    return [$content_type, $content];
  }

  # create a temp file with the data
  $path = tempnam(sys_get_temp_dir(), 'disp-');
  $temp = fopen($path, 'w');
  $data = fopen($pipe, 'r');

  stream_copy_to_stream($data, $temp);

  fclose($temp);
  fclose($data);

  # type-path tuple
  return [$content_type, $path];
}

# prints out no-cache headers
function nocache() {

  $stamp = gmdate('D, d M Y H:i:s', $_SERVER['REQUEST_TIME']).' GMT';

  # dump no-cache headers
  header('Expires: Tue, 13 Mar 1979 18:00:00 GMT');
  header('Last-Modified: '.$stamp);
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
}

# maps directly to json_encode, but renders JSON headers as well
function json() {

  $json = call_user_func_array('json_encode', func_get_args());
  $err = json_last_error();

  # trigger a user error for failed encodings
  if ($err !== JSON_ERROR_NONE) {
    throw new RuntimeException(
      "JSON encoding failed [{$err}].",
      500
    );
  }

  header('Content-type: application/json');
  print $json;
}

# shortcut for http_response_code()
function status($code) {
  return http_response_code($code);
}

# shortcut for dumping a redirect header (no longer exits)
function redirect($path, $code = 302, $halt = false) {
  header("Location: {$path}", true, $code);
  $halt && exit;
}

# function for mapping actions to routes
function map() {

  $argv = func_get_args();
  $data = &$GLOBALS['noodlehaus\dispatch']['routes'];

  # try to figure out how we were called
  switch (count($argv)) {

    # complete params (method, path, handler)
    case 3:
      foreach ((array) $argv[0] as $verb) {
        $data['explicit'][strtoupper($verb)][] = [
          '/'.trim($argv[1], '/'),
          $argv[2]
        ];
      }
      break;

    # either (path, handler) or (code, handler)
    case 2:
      $argv[0] = (array) $argv[0];
      if (ctype_digit($argv[0][0])) {
        foreach ($argv[0] as $code)
          $data['errors'][intval($code)] = $argv[1];
      } else {
        foreach ($argv[0] as $path)
          $data['any'][] = ['/'.trim($path, '/'), $argv[1]];
      }
      break;

    # any method and any path (just one for this, replace ref)
    case 1:
      $data['all'] = $argv[0];
      break;

    # everything else
    default:
      throw new BadFunctionCallException(
        'Invalid number of arguments.',
        500
      );
  }
}

# hooks actions to route symbols
function hook($name, $func) {
  $GLOBALS['noodlehaus\dispatch']['routes']['hooks'][$name] = $func;
}

# triggers an HTTP error code and invokes any mapped error handlers
function error() {

  $argc = func_num_args();
  $argv = func_get_args();

  if (!$argc) {
    throw new BadFunctionCallException(
      'Invalid number of arguments.',
      500
    );
  }

  $code = $argv[0];
  $data = &$GLOBALS['noodlehaus\dispatch']['routes'];

  $func = (
    isset($data['errors'][$code]) ?
    $data['errors'][$code] :
    function ($code) { return http_response_code($code); }
  );

  http_response_code($code);

  return call_user_func_array($func, $argv);
}

# dispatches the current request against our handlers/actions
function dispatch() {

  $argv = func_get_args();
  $data = &$GLOBALS['noodlehaus\dispatch']['routes'];

  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  $path = trim($path, '/');

  # strip url from request URI
  if ($base = config('url')) {
    $base = trim(parse_url($base, PHP_URL_PATH), '/');
    $path = preg_replace('@^'.preg_quote($base).'@', '', $path);
  }

  # if no rewrite, strip router file from request URI
  if ($stub = config('router')) {
    $path = preg_replace(
      '@^/?'.preg_quote(trim($stub, '/')).'@i',
      '',
      $path
    );
  }

  $path = trim($path, '/');
  $verb = strtoupper($_SERVER['REQUEST_METHOD']);

  # for POST requests, check for method override header or _method
  if ($verb == 'POST') {
    if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
      $verb = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    } else {
      $verb = isset($_POST['_method']) ? strtoupper($_POST['_method']) : $verb;
    }
  }

  # set any mapping as base, then append exp_mapping if any
  $maps = $data['any'];
  if (isset($data['explicit'][$verb])) {
    $maps = array_merge($data['explicit'][$verb], $maps);
  }

  $rexp = null;
  $func = null;
  $vals = null;

  # try to see if we have any matching route
  foreach ($maps as $temp) {

    list($rexp, $call) = $temp;

    $rexp = trim($rexp, '/');

    # replace named params (support regex formats)
    $rexp = preg_replace(
      [
        '@<([^:]+)>@U', # <param> => <param>[^/]+
        '@<([^:]+)(:(.+))?>@U', # <param:...> => (?<param>...)
      ],
      [
        '<$1:[^/]+>',
        '(?<$1>$3)',
      ],
      $rexp
    );

    if (!preg_match('@^'.$rexp.'$@', $path, $vals)) {
      continue;
    }

    $func = $call;
    break;
  }

  # valid handler, try to parse out route symbol values
  if ($func && is_callable($func)) {

    # remove top group from vals
    array_shift($vals);

    # extract route symbols and run the hook()s
    if ($vals) {

      # extract any route symbol values
      $toks = array_filter(array_keys($vals), 'is_string');
      $vals = array_map('rawurldecode', array_intersect_key(
        $vals,
        array_flip($toks)
      ));

      # if we have symbol hooks, run them
      if (count($data['hooks'])) {

        foreach ($vals as $key => $val) {

          if (!isset($data['hooks'][$key])) {
            continue;
          }

          $hook = $data['hooks'][$key];

          $vals[$key] = call_user_func_array(
            $hook,
            array_merge((array) $val, $argv)
          );
        }
      }

      # insert symbol values before dispatch() args
      array_unshift($argv, $vals);
    }

  } else {

    # no matching handler, try to use 'all', or 404
    if (is_callable($data['all'])) {
      $func = $data['all'];
    } else {
      $func = 'error';
      array_unshift($argv, 404);
    }

  }

  return call_user_func_array($func, $argv);
}

# state (routes, handlers, etc)
$GLOBALS['noodlehaus\dispatch'] = [
  'config' => [],
  'stash' => [],
  'request_headers' => [],
  'routes' => [
    'all' => null,
    'any' => [],
    'hooks' => [],
    'explicit' => [],
    'errors' => []
  ]
];
