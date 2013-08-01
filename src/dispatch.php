<?php
/**
 * @author Jesus A. Domingo
 * @license MIT
 */

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400)
  error(500, 'dispatch requires at least PHP 5.4 to run.');

/**
 * Function for setting http error code handlers and for
 * triggering them. Execution stops after an error callback
 * handler finishes.
 *
 * @param int $code http status code to use
 * @param callable optional, callback for the error
 *
 * @return void
 */
function error($code, $callback = null) {

  static $error_callbacks = [];

  $code = (string) $code;

  if (is_callable($callback)) {
    $error_callbacks[$code][] = $callback;
  } else {

    $message = (is_string($callback) ? $callback : 'Page Error');

    if (PHP_SAPI !== 'cli')
      @header("HTTP/1.1 {$code} {$message}", true, (int) $code);

    if (isset($error_callbacks[$code]))
      foreach ($error_callbacks[$code] as $cb)
        call_user_func($cb, $code);
    else
      echo "{$code} {$message}\n";

    exit;
  }
}

/**
 * Sets or gets an entry from the loaded config.ini file. If the $key passed
 * is 'source', it expects $value to be a path to an ini file to load. Calls
 * to config('source', 'inifile.ini') will aggregate the contents of the ini
 * file into config().
 *
 * @param string $key config setting to set or get
 * @param string $value optional, If present, sets $key to this $value.
 *
 * @return mixed|null value
 */
function config($key, $value = null) {

  static $_config = [];

  if ($key === 'source' && file_exists($value))
    $_config = array_merge($_config, parse_ini_file($value, true));
  else if ($value === null)
    return (isset($_config[$key]) ? $_config[$key] : null);

  return ($_config[$key] = $value);
}

/**
 * Returns the string contained by 'dispatch.url' in config.ini.
 * This includes the hostname and path. If called with $path_only set to
 * true, it will return only the path section of the URL.
 *
 * @param boolean $path_only defaults to false, true means return only the path
 * @return string value pointed to by 'dispatch.url' in config.ini.
 */
function site($path_only = false) {

  if (!config('dispatch.url'))
    return null;

  if ($path_only)
    return rtrim(parse_url(config('dispatch.url'), PHP_URL_PATH), '/');

  return rtrim(config('dispatch.url'), '/').'/';
}

/**
 * Utility for setting cross-request messages using cookies,
 * referred to as flash messages (invented by Rails folks).
 * Calling flash('key') will return the message and remove
 * the message making it unavailable in the following request.
 * Calling flash('key', 'message', true) will store that message
 * for the current request but not available for the next one.
 *
 * @param string $key name of the flash message
 * @param string $msg string to store as the message
 * @param bool $now if the message is available immediately
 *
 * @return $string message for the key
 */
function flash($key, $msg = null, $now = false) {

  static $x = [];

  $f = config('dispatch.flash_cookie');

  if (!$f)
    error(500, "config('dispatch.flash_cookie') is not set.");

  if ($c = cookie($f))
    $c = json_decode($c, true);
  else
    $c = [];

  if ($msg == null) {

    if (isset($c[$key])) {
      $x[$key] = $c[$key];
      unset($c[$key]);
      cookie($f, json_encode($c));
    }

    return (isset($x[$key]) ? $x[$key] : null);
  }

  if (!$now) {
    $c[$key] = $msg;
    cookie($f, json_encode($c));
  }

  return ($x[$key] = $msg);
}

/**
 * Convenience wrapper for urlencode()
 *
 * @param string $str string to encode.
 *
 * @return string url encoded string
 */
function u($str) {
  return urlencode($str);
}

/**
 * Convenience wrapper for htmlentities().
 *
 * @param string $str string to encode
 * @param string $enc encoding to use.
 * @param string $flags htmlentities() flags
 *
 * @return string encoded string
 */
function h($str, $flags = ENT_QUOTES, $enc = 'UTF-8') {
  return htmlentities($str, $flags, $enc);
}

/**
 * Helper for getting values from $_GET, $_POST and route
 * symbols. If called with no arguments, it returns all param
 * values.
 *
 * @param string $name optional. parameter to get the value for
 * @param mixed $default optional. default value for param
 *
 * @return mixed param value.
 */
function params($name = null, $default = null) {

  static $source = null;

  if (!$source)
    $source = array_merge($_GET, $_POST);

  if (is_string($name))
    return (isset($source[$name]) ? $source[$name] : $default);
  else if ($name == null)
    return $source;

  // used by on() for merging in route symbols
  if (is_array($name))
    $source = array_merge($source, $name);
}

/**
 * Wraps around $_SESSION
 *
 * @param string $name name of session variable to set
 * @param mixed $value value for the variable. Set this to null to
 *   unset the variable from the session.
 *
 * @return mixed value for the session variable
 */
function session($name, $value = null) {

  static $status = -1;

  if ($status < 0) {
    if (($status = session_status()) === PHP_SESSION_DISABLED)
      error(500, 'call to session() failed, sessions are disabled');
    else if ($status === PHP_SESSION_NONE)
      session_start();
  }

  if (func_num_args() === 1)
    return (isset($_SESSION[$name]) ? $_SESSION[$name] : null);

  if ($value === null)
    unset($_SESSION[$name]);
  else
    $_SESSION[$name] = $value;
}

/**
 * Wraps around $_COOKIE and setcookie().
 *
 * @param string $name name of the cookie to get or set
 * @param string $value optional. value to set for the cookie
 * @param integer $expire default 1 year. expiration in seconds.
 * @param string $path default '/'. path for the cookie.
 *
 * @return string value if only the name param is passed.
 */
function cookie($name, $value = null, $expire = 31536000, $path = '/') {
  if (func_num_args() === 1)
    return (isset($_COOKIE[$name]) ? $_COOKIE[$name] : null);
  setcookie($name, $value, time() + $expire, $path);
}

/**
 * Convenience function for reading in the request body. JSON
 * and form-urlencoded content are automatically parsed and returned
 * as arrays.
 *
 * @return mixed raw string or decoded JSON object
 */
function request_body() {

  $content_type = isset($_SERVER['HTTP_CONTENT_TYPE']) ?
    $_SERVER['HTTP_CONTENT_TYPE'] :
    $_SERVER['CONTENT_TYPE'];

  $content = file_get_contents('php://input');

  if ($content_type[0] == 'application/json')
    $content = json_decode($content);
  else if ($content_type[0] == 'application/x-www-form-urlencoded')
    parse_str($content, $content);

  return $content;
}

/**
 * File upload wrapper. Returns a hash containing file
 * upload info. Skips invalid uploads based on
 * is_uploaded_file() check.
 *
 * @param string $name input file field name to check.
 *
 * @param array info of file if found.
 */
function upload($name) {

  if (!isset($_FILES[$name]))
    return null;

  $result = null;

  // if file field is an array
  if (is_array($_FILES[$name]['name'])) {

    $result = [];

    // consolidate file info
    foreach ($_FILES[$name] as $k1 => $v1)
      foreach ($v1 as $k2 => $v2)
        $result[$k2][$k1] = $v2;

    // remove invalid uploads
    foreach ($result as $i => $f)
      if (!is_uploaded_file($f['tmp_name']))
        unset($result[$i]);

    // if no entries, null, else, return it
    $result = (!count($result) ? null : array_values($result));

  } else {
    // only if file path is valid
    if (is_uploaded_file($_FILES[$name]['tmp_name']))
      $result = $_FILES[$name];
  }

  // null if no file or invalid, hash if valid
  return $result;
}

/**
 * A utility for passing values between scopes. If $value
 * is passed, $name will be set to $value. If $value is not
 * passed, the value currently mapped against $name will be
 * returned instead.
 *
 * @param string $name name of variable to store.
 * @param mixed $value optional, value to store against $name
 *
 * @return mixed value mapped to $name
 */
function scope($name, $value = null) {

  static $_stash = [];

  if ($value === null)
    return isset($_stash[$name]) ? $_stash[$name] : null;

  return ($_stash[$name] = $value);
}

/**
 * Returns the client's IP address.
 *
 * @return string client's ip address.
 */
function ip() {

  if (isset($_SERVER['HTTP_CLIENT_IP']))
    return $_SERVER['HTTP_CLIENT_IP'];
  else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
    return $_SERVER['HTTP_X_FORWARDED_FOR'];

  return $_SERVER['REMOTE_ADDR'];
}

/**
 * Performs an HTTP redirect.
 *
 * @param int|string http code for redirect, or path to redirect to
 * @param string|bool path to redirect to, or condition for the redirect
 * @param bool condition for the redirect, true means it happens
 *
 * @return void
 */
function redirect($path, $code = 302, $condition = true) {
  if (!$condition)
    return;
  @header("Location: {$path}", true, $code);
  exit;
}

/**
 * Returns the contents of the template partial $view, using
 * $locals (optional).
 *
 * @param string $view path to partial
 * @param array $locals optional, hash to load as local variables in the partial's scope.
 *
 * @return string content of the partial.
 */
function partial($view, $locals = null) {

  if (($view_root = config('dispatch.views')) == null)
    error(500, "config('dispatch.views') is not set.");

  if (is_array($locals) && count($locals))
    extract($locals, EXTR_SKIP);

  $path = basename($view);
  $view = preg_replace('/'.$path.'$/', "_{$path}", $view);
  $view = $view_root.DIRECTORY_SEPARATOR.$view.'.html.php';

  $html = '';

  if (file_exists($view)) {
    ob_start();
    require $view;
    $html = ob_get_clean();
  } else {
    error(500, "partial [{$view}] not found");
  }

  return $html;
}

/**
 * Convenience function for storing/fetching content to be
 * plugged into the layout within render().
 *
 * @param string $value optional, value to use as content.
 *
 * @return string content
 */
function content($value = null) {
  return scope('$content$', $value);
}

/**
 * Renders the contents of $view using $locals (optional), into
 * $layout (optional). If $layout === false, no layout will be used.
 *
 * @param string $view path to the view file to render
 * @param array $locals optional, hash to load into $view's scope
 * @param string|bool path to the layout file to use, or if no layout is to be used.
 *
 * @return string contents of the view + layout
 */
function render($view, $locals = null, $layout = null) {

  if (($view_root = config('dispatch.views')) == null)
    error(500, "config('dispatch.views') is not set.");

  if (is_array($locals) && count($locals))
    extract($locals, EXTR_SKIP);

  ob_start();
  include $view_root.DIRECTORY_SEPARATOR.$view.'.html.php';
  content(trim(ob_get_clean()));

  if ($layout !== false) {

    if ($layout == null) {
      $layout = config('dispatch.layout');
      $layout = ($layout == null) ? 'layout' : $layout;
    }

    $layout = $view_root.DIRECTORY_SEPARATOR.$layout.'.html.php';

    ob_start();
    require $layout;
    echo trim(ob_get_clean());

  } else {
    echo content();
  }

  exit;
}

/**
 * Spit headers that force cache volatility.
 *
 * @param string $content_type optional, defaults to text/html.
 *
 * @return void
 */
function nocache() {
  header('Expires: Tue, 13 Mar 1979 18:00:00 GMT');
  header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
}

/**
 * Dump a JSON response along with the appropriate headers.
 *
 * @param mixed $obj object to serialize into JSON
 * @param string $func for JSONP output, this is the callback name
 *
 * @return void
 */
function json_out($obj, $func = null) {
  nocache();
  if (!$func) {
    header('Content-type: application/json');
    echo json_encode($obj);
  } else {
    header('Content-type: application/javascript');
    echo ";{$func}(".json_encode($obj).");";
  }
  exit;
}

/**
 * Creates callbacks (filters) against certain
 * symbols within a route. Whenever $sym is encountered
 * in a route, the filter is invoked.
 *
 * @param string $sym symbol to create a filter for
 * @param callable|mixed filter or value to pass to the filter
 *
 * @return void
 */
function filter($symbol, $callback = null) {

  static $filter_callbacks = [];

  if (is_callable($callback)) {
    $filter_callbacks[$symbol][] = $callback;
    return;
  }

  foreach ($symbol as $sym => $val) {
    if (isset($filter_callbacks[$sym])) {
      foreach ($filter_callbacks[$sym] as $callback) {
        call_user_func($callback, $val);
      }
    }
  }
}

/**
 * Function for mapping callbacks to be invoked before each request.
 * Order of execution depends on the order of mapping.
 *
 * @param callable $callback routine to invoke before each request.
 *
 * @return void
 */
function before($callback = null) {

  static $before_callbacks = [];

  if ($callback === null) {
    foreach ($before_callbacks as $callback)
      call_user_func($callback);
  } else {
    $before_callbacks[] = $callback;
  }
}

/**
 * Function for mapping callbacks to be invoked after each request.
 * Order of execution depends on the order of mapping.
 *
 * @param callable $callback routine to invoke after each request.
 *
 * @return void
 */
function after($callback = null) {

  static $after_callbacks = [];

  if ($callback === null) {
    foreach ($after_callbacks as $callback)
      call_user_func($callback);
  } else {
    $after_callbacks[] = $callback;
  }
}

/**
 * Maps a callback or invokes a callback for requests
 * on $pattern. If $callback is not set, $pattern
 * is matched against all routes for $method, and the
 * the mapped callback for the match is invoked. If $callback
 * is set, that callback is mapped against $pattern for $method
 * requests.
 *
 * @param string $method HTTP request method or method + path
 * @param string $pattern path or callback
 * @param callable $callback optional, handler to map
 *
 * @return void
 */
function on($method, $path, $callback = null) {

  // callback map by request type
  static $routes = [
    'HEAD' => [],
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'PATCH' => [],
    'DELETE' => []
  ];

  // we don't want slashes in both ends
  $path = trim($path, '/');

  // a callback was passed, so we create a route defiition
  if (is_callable($callback)) {

    // create the regex for this route
    $regex = preg_replace_callback('@:\w+@', function ($matches) {
      return '(?<'.str_replace(':', '', $matches[0]).'>[^/]+)';
    }, $path);

    // create the list of methods to map to
    $method = (array) $method;

    // wildcard method means for all supported methods
    if (in_array('*', $method)) {
      $method = array_keys($routes);
    } else {
      array_walk($method, function (&$m) { $m = strtoupper($m); });
      $method = array_intersect(array_keys($routes), $method);
    }

    // create a route entry for this path on every method
    foreach ($method as $m)
      $routes[$m][$path] = ['regex' => '@^'.$regex.'$@i', 'callback' => $callback];

  } else {

    // not a string? invalid
    if (!is_string($method))
      error(400, 'Invalid method');

    // then normalize
    $method = strtoupper($method);

    // do we have a method override?
    if (params('_method'))
      $method = strtoupper(params('_method'));

    // for invokation, only support strings
    if (!in_array($method, array_keys($routes)))
      error(400, 'Method not supported');

    // callback is null, so this is a route invokation. look up the callback.
    foreach ($routes[$method] as $pattern => $info) {

      // skip non-matching routes
      if (!preg_match($info['regex'], $path, $values))
        continue;

      // construct the params for the callback
      array_shift($values);
      preg_match_all('@:([\w]+)@', $pattern, $symbols, PREG_PATTERN_ORDER);
      $symbols = $symbols[1];
      $values = array_intersect_key($values, array_flip($symbols));

      // decode values
      array_walk($values, function (&$val, $key) {
        $val = urldecode($val);
      });

      // if we have symbols, init params and run filters
      if (count($symbols)) {
        params($values);
        filter($values);
      }

      // invoke callback
      call_user_func_array($info['callback'], array_values($values));

      // done
      return;
    }

    // we got a 404
    error(404, 'Page not found');
  }
}

/**
 * Entry point for the library.
 *
 * @param string $method optional, for testing in the cli
 * @param string $path optional, for testing in the cli
 *
 * @return void
 */
function dispatch($method = null, $path = null) {

  // see if we were invoked with params
  $method = ($method ? $method : $_SERVER['REQUEST_METHOD']);

  // normalize routing base, if site is in sub-dir
  $path = parse_url($path ? $path : $_SERVER['REQUEST_URI'], PHP_URL_PATH);
  $root = config('dispatch.router');
  $base = site(true);

  // strip base from path
  if ($base !== null)
    $path = preg_replace('@^'.preg_quote($base).'@', '', $path);

  // if we have a routing file (no mod_rewrite), strip it from the URI
  if ($root)
    $path = preg_replace('@^/?'.preg_quote(trim($root, '/')).'@i', '', $path);

  // setup shutdown func for after() callbacks
  register_shutdown_function(function () {
    after();
  });

  // call all before() callbacks
  before();

  // match it
  on($method, $path);
}
?>
