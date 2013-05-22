<?php
/**
 * @author Jesus A. Domingo
 * @license MIT
 */

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300)
  error(500, 'dispatch requires at least PHP 5.3 to run.');

/**
 * Sends a log message to the file pointed to by 'debug.log'.
 *
 * @param string $message string to put on the logger.
 *
 * @return void
 */
function _log($message) {
  if (config('debug.enable') == true) {
    if (php_sapi_name() !== 'cli') {
      $file = config('debug.log');
      $type = $file ? 3 : 0;
      error_log("{$message}\n", $type, $file);
    } else {
      echo $message."\n";
    }
  }
}

/**
 * Returns the string contained by 'site.url' in config.ini.
 * This includes the hostname and path.
 *
 * @return string value pointed to by 'site.url' in config.ini.
 */
function site_url() {

  if (config('site.url') == null)
    error(500, '[site.url] is not set');

  // Forcing the forward slash
  return rtrim(config('site.url'), '/').'/';
}

/**
 * Returns the path section of 'site.url' from config.ini.
 *
 * @return string path section of 'site.url'.
 */
function site_path() {

  static $_path;

  if (config('site.url') == null)
    error(500, '[site.url] is not set');

  if (!$_path)
    $_path = rtrim(parse_url(config('site.url'), PHP_URL_PATH), '/');

  return $_path;
}

/**
 * A convenience function over header() for printing out
 * HTTP error messages. If used in CLI mode, it die()s with
 * the code and the message, instead.
 *
 * @param int $code http status code to use
 * @param string $message string to display as content.
 *
 * @return void
 */
function error($code, $message) {

  if (php_sapi_name() !== 'cli')
    @header("HTTP/1.0 {$code} {$message}", true, $code);

  die("{$code} - {$message}");
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

  static $_config = array();

  if ($key === 'source' && file_exists($value))
    $_config = array_merge($_config, parse_ini_file($value, true));
  else if ($value == null)
    return (isset($_config[$key]) ? $_config[$key] : null);
  else
    return ($_config[$key] = $value);

  return $value;
}

/**
 * Cookie-safe and URL-safe version of base64_encode()
 *
 * @param string $str string to encode
 *
 * @return string encoded string
 */
function to_b64($str) {
  $str = base64_encode($str);
  $str = preg_replace('/\//', '_', $str);
  $str = preg_replace('/\+/', '.', $str);
  $str = preg_replace('/\=/', '-', $str);
  return trim($str, '-');
}

/**
 * Decodes a to_b64() encoded string.
 *
 * @param string $str encoded string
 *
 * @return string decoded string
 */
function from_b64($str) {
  $str = preg_replace('/\_/', '/', $str);
  $str = preg_replace('/\./', '+', $str);
  $str = preg_replace('/\-/', '=', $str);
  $str = base64_decode($str);
  return $str;
}

if (extension_loaded('mcrypt')) {

  /**
   * Encryption function that uses the mcrypt extension.
   *
   * @param string $decoded string to encrypt
   * @param int $algo one of the MCRYPT_ciphername constants
   * @param int $mode one of the MCRYPT_MODE_modename constants
   *
   * @return string encrypted string + iv code
   */
  function encrypt($decoded, $algo = MCRYPT_RIJNDAEL_256, $mode = MCRYPT_MODE_CBC) {

    if (($secret = config('cookies.secret')) == null)
      error(500, '[cookies.secret] is not set');

    $secret  = mb_substr($secret, 0, mcrypt_get_key_size($algo, $mode));
    $iv_size = mcrypt_get_iv_size($algo, $mode);
    $iv_code = mcrypt_create_iv($iv_size, MCRYPT_DEV_URANDOM);
    $encrypted = to_b64(mcrypt_encrypt($algo, $secret, $decoded, $mode, $iv_code));

    return sprintf('%s|%s', $encrypted, to_b64($iv_code));
  }

  /**
   * Decrypts a string encrypted by encrypt().
   *
   * @param string $encoded encrypted string
   * @param int $algo one of the MCRYPT_ciphername constants
   * @param int $mode one of the MCRYPT_MODE_modename constants
   *
   * @return string decrypted string
   */
  function decrypt($encoded, $algo = MCRYPT_RIJNDAEL_256, $mode = MCRYPT_MODE_CBC) {

    if (($secret = config('cookies.secret')) == null)
      error(500, '[cookies.secret] is not set');

    $secret  = mb_substr($secret, 0, mcrypt_get_key_size($algo, $mode));
    list($enc_str, $iv_code) = explode('|', $encoded);
    $enc_str = from_b64($enc_str);
    $iv_code = from_b64($iv_code);
    $enc_str = mcrypt_decrypt($algo, $secret, $enc_str, $mode, $iv_code);

    return rtrim($enc_str, "\0");
  }

}

/**
 * Wraps around setcookie() so it can encrypt the values if mcrypt is loaded.
 *
 * @param string $name name of the cookie
 * @param string $value value of the cookie
 * @param int $expire optional, how long the cookie lives
 * @param string $path path for the cookie
 *
 * @return void
 */
function set_cookie($name, $value, $expire = 31536000, $path = '/') {
  $value = (function_exists('encrypt') ? encrypt($value) : $value);
  setcookie($name, $value, time() + $expire, $path);
}

/**
 * Wraps $_COOKIE access to automatically decrypt() values if mcrypt
 * is detected.
 *
 * @param string $name name of the cookie to get
 *
 * @return string cookie value
 */
function get_cookie($name) {

  $value = from($_COOKIE, $name);

  if ($value)
    $value = (function_exists('decrypt') ? decrypt($value) : $value);

  return $value;
}

/**
 * Wraps around setcookie() to allow removal of multiple cookies
 *
 * @param string $v,... cookies to unset
 *
 * @return void
 */
function delete_cookie() {
  $cookies = func_get_args();
  foreach ($cookies as $ck)
    setcookie($ck, '', -10, '/');
}

if (extension_loaded('apc')) {

  /**
   * Stores the value returned by $func into apc against $key if $func is passed,
   * for $ttl seconds. If $func is not passed, the value mapped to $key is returned.
   *
   * @param string $key cache entry to fetch or store into
   * @param callable $func function whose return value is stored against $key
   * @param int $ttl optional, time-to-live for $key, in seconds
   *
   * @return mixed data cached against $key
   */
  function cache($key, $func, $ttl = 0) {
    if (($data = apc_fetch($key)) === false) {
      $data = call_user_func($func);
      if ($data !== null)
        apc_store($key, $data, $ttl);
    }
    return $data;
  }

  /**
   * Wraps around apc_cas() but accepts a callable as 2nd parameter.
   *
   * @param string $key cache entry to store into
   * @param mixed $old old value to use as state
   * @param callable $func function whose return value will be used as new value
   *
   * @return void
   */
  function cache_cas($key, $old, $func) {

    $oldval = apc_fetch($key);
    $newval = call_user_func($func);

    apc_cas($key, $oldval, $newval);
  }

  /**
   * Invalidates a key or list of keys from the cache.
   *
   * @param string $v,... key or keys to invalidate from the cache.
   *
   * @return void
   */
  function cache_invalidate() {
    foreach (func_get_args() as $key)
      apc_delete($key);
  }

}

/**
 * Form helper that stores form field warnings into
 * a hash that can be fetched later. If no arguments are
 * passed, it returns the number of warnings it accumulated.
 * Passing just the $name will return all warnings for that
 * field. Passing both $name and $message will map $message
 * as an error for $name. Passing '*' as the only argument
 * will return hash of all the errors.
 *
 * @param string $name optional, name of field
 * @param string $message optional, message to map against $name
 *
 * @return mixed count of errors or hash of errors, or single error
 */
function warn($name = null, $message = null) {

  static $warnings = array();

  if ($name == '*')
    return $warnings;

  if (!$name)
    return count(array_keys($warnings));

  if (!$message)
    return isset($warnings[$name]) ? $warnings[$name] : null ;

  $warnings[$name] = $message;
}

/**
 * Convenience wrapper for urlencode()
 *
 * @param string $str string to encode.
 *
 * @return string url encoded string
 */
function _u($str) {
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
function _h($str, $enc = 'UTF-8', $flags = ENT_QUOTES) {
  return htmlentities($str, $flags, $enc);
}

/**
 * Utility for getting values from arrays, ie. $_GET, $_POST.
 * If $name is not set within $source, $default will be returned
 * instead.
 *
 * @param array $source array to get data from
 * @param string $name key to lookup in $source
 * @param mixed $default optional, value to use for unset keys
 *
 * @return mixed whatever $name maps to, or $default
 */
function from($source, $name, $default = null) {
  if (is_array($name)) {
    $data = array();
    foreach ($name as $k)
      $data[$k] = isset($source[$k]) ? $source[$k] : $default ;
    return $data;
  }
  return isset($source[$name]) ? $source[$name] : $default ;
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
function stash($name, $value = null) {

  static $_stash = array();

  if ($value === null)
    return isset($_stash[$name]) ? $_stash[$name] : null;

  $_stash[$name] = $value;

  return $value;
}

/**
 * Utility for checking the request method. If $verb is passed,
 * that $verb is checked against the current request method. If
 * $verb wasn't passed, it returns the request method.
 *
 * @param string $verb optional, verb to check against REQUEST_METHOD
 *
 * @return bool|string if $verb is passed, bool for if it matches, else, REQUEST_METHOD
 */
function method($verb = null) {

  if ($verb == null || (strtoupper($verb) == strtoupper($_SERVER['REQUEST_METHOD'])))
    return strtoupper($_SERVER['REQUEST_METHOD']);

  error(400, 'bad request');
}

/**
 * Returns the client's IP address.
 *
 * @return string client's ip address.
 */
function client_ip() {

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
function redirect(/* $code_or_path, $path_or_cond, $cond */) {

  $argv = func_get_args();
  $argc = count($argv);

  $path = null;
  $code = 302;
  $cond = true;

  switch ($argc) {
    case 3:
      list($code, $path, $cond) = $argv;
      break;
    case 2:
      if (is_string($argv[0]) ? $argv[0] : $argv[1]) {
        $code = 302;
        $path = $argv[0];
        $cond = $argv[1];
      } else {
        $code = $argv[0];
        $path = $argv[1];
      }
      break;
    case 1:
      if (!is_string($argv[0]))
        error(500, 'bad call to redirect()');
      $path = $argv[0];
      break;
    default:
      error(500, 'bad call to redirect()');
  }

  $cond = (is_callable($cond) ? !!call_user_func($cond) : !!$cond);

  if (!$cond)
    return;

  header('Location: '.$path, true, $code);
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

  if (is_array($locals) && count($locals)) {
    extract($locals, EXTR_SKIP);
  }

  if (($view_root = config('views.root')) == null)
    error(500, "[views.root] is not set");

  $path = basename($view);
  $view = preg_replace('/'.$path.'$/', "_{$path}", $view);
  $view = "{$view_root}/{$view}.html.php";

  if (file_exists($view)) {
    ob_start();
    require $view;
    return ob_get_clean();
  } else {
    error(500, "partial [{$view}] not found");
  }

  return '';
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
  return stash('$content$', $value);
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

  if (is_array($locals) && count($locals))
    extract($locals, EXTR_SKIP);

  if (($view_root = config('views.root')) == null)
    error(500, "[views.root] is not set");

  ob_start();
  include "{$view_root}/{$view}.html.php";
  content(trim(ob_get_clean()));

  if ($layout !== false) {

    if ($layout == null) {
      $layout = config('views.layout');
      $layout = ($layout == null) ? 'layout' : $layout;
    }

    $layout = "{$view_root}/{$layout}.html.php";

    header('Content-type: text/html; charset=utf-8');

    ob_start();
    require $layout;
    echo trim(ob_get_clean());

  } else {
    echo content();
  }
}

/**
 * Utility function for spitting out JSON content.
 *
 * @param mixed $obj value to serialize as JSON
 * @param int $code optional, http code to use in the response.
 *
 * @return void
 */
function json($obj, $code = 200) {
  header('Content-type: application/json', true, $code);
  echo json_encode($obj);
  exit;
}

/**
 * Creates conditions that can be invoked to determine
 * if execution of an action for a route continues or not.
 * If argument 2 is a callable, that becomes the handler
 * for the condition. Otherwise, call becomes an invocation of the
 * condition with all remaining arguments are passed to the condition.
 *
 * @param string $name name of the condition
 * @param callable|mixed[] $cb_or_arg,... callback for condition, or args during invocation
 *
 * @return void
 */
function condition() {

  static $cb_map = array();

  $argv = func_get_args();
  $argc = count($argv);

  if (!$argc)
    error(500, 'bad call to condition()');

  $name = array_shift($argv);
  $argc = $argc - 1;

  if (!$argc && is_callable($cb_map[$name]))
    return call_user_func($cb_map[$name]);

  if (is_callable($argv[0]))
    return ($cb_map[$name] = $argv[0]);

  if (is_callable($cb_map[$name]))
    return call_user_func_array($cb_map[$name], $argv);

  error(500, 'condition ['.$name.'] is undefined');
}

/**
 * Inserts a callback to be invoked before handling of requests
 * or invokes all queued callbacks, if no argument was passed.
 *
 * @param callable|string $cb_or_path if callable, adds middleware, invokes all otherwise
 *
 * @return void
 */
function middleware($cb_or_path = null) {

  static $cb_map = array();

  if ($cb_or_path == null || is_string($cb_or_path)) {
    foreach ($cb_map as $cb)
      call_user_func($cb, $cb_or_path);
  } else {
    array_push($cb_map, $cb_or_path);
  }
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
function filter($sym, $cb_or_val = null) {

  static $cb_map = array();

  if (is_callable($cb_or_val)) {
    $cb_map[$sym] = $cb_or_val;
    return;
  }

  if (is_array($sym) && count($sym) > 0) {
    foreach ($sym as $s) {
      $s = substr($s, 1);
      if (isset($cb_map[$s]) && isset($cb_or_val[$s]))
        call_user_func($cb_map[$s], $cb_or_val[$s]);
    }
    return;
  }

  error(500, 'bad call to filter()');
}

/**
 * Converts a route into a regular expression. Used within route().
 *
 * @param string $route string to convert.
 *
 * @return string regular expression
 */
function route_to_regex($route) {

  $route = preg_replace_callback('@:[\w]+@i', function ($matches) {
    $token = str_replace(':', '', $matches[0]);
    return '(?P<'.$token.'>[a-z0-9_\0-\.]+)';
  }, $route);

  $route = rtrim($route, '/');
  $route = '@^'.(!strlen($route) ? '/' : $route).'$@i';

  return $route;
}

/**
 * Maps a callback or invokes a callback for requests
 * on $pattern. If $callback is not set, $pattern
 * is matched against all routes for $method, and the
 * the mapped callback for the match is invoked. If $callback
 * is set, that callback is mapped against $pattern for $method
 * requests.
 *
 * @param string $method HTTP request method to map to
 * @param string $pattern regex or url path
 * @param callable $callback optional, handler to map
 *
 * @return void
 */
function route($method, $pattern, $callback = null) {

  // callback map by request type
  static $route_map = array(
    'GET' => array(),
    'POST' => array(),
    'PUT' => array(),
    'DELETE' => array()
  );

  $method = strtoupper($method);

  if (!in_array($method, array('GET', 'POST')))
    error(500, 'Only GET and POST are supported');

  // a callback was passed, so we create a route defiition
  if ($callback !== null) {

    // create a route entry for this pattern
    $route_map[$method][$pattern] = array(
      'xp' => route_to_regex($pattern),
      'cb' => $callback
    );

  } else {

    // callback is null, so this is a route invokation. look up the callback.
    foreach ($route_map[$method] as $pat => $obj) {

      // if the requested uri ($pat) has a matching route, let's invoke the cb
      if (!preg_match($obj['xp'], $pattern, $vals))
        continue;

      // call middleware
      middleware($pattern);

      // construct the params for the callback
      array_shift($vals);
      preg_match_all('@:([\w]+)@', $pat, $keys, PREG_PATTERN_ORDER);
      $keys = array_shift($keys);
      $argv = array();

      foreach ($keys as $index => $id) {
        $id = substr($id, 1);
        if (isset($vals[$id]))
          array_push($argv, trim(urldecode($vals[$id])));
      }

      // call filters if we have symbols
      if (count($keys))
        filter(array_values($keys), $vals);

      // if cb found, invoke it
      if (is_callable($obj['cb']))
        call_user_func_array($obj['cb'], $argv);

      // leave after first match
      break;

    }
  }

}

/**
 * Utility for mapping $cb (callable) to DELETE requests on
 * $path.
 *
 * @param string $path route to create a handler for
 * @param callable $cb handler to map against DELETEs on $path
 *
 * @return void
 */
function del($path, $cb) {
  route('DELETE', $path, $cb);
}

/**
 * Utility for mapping $cb (callable) to PUT requests on
 * $path.
 *
 * @param string $path route to create a handler for
 * @param callable $cb handler to map against PUTs on $path
 *
 * @return void
 */
function put($path, $cb) {
  route('PUT', $path, $cb);
}

/**
 * Utility for mapping $cb (callable) for GET requests on
 * $path.
 *
 * @param string $path route to create a handler for
 * @param callable $cb handler to map against GETs on $path
 *
 * @return void
 */
function get($path, $cb) {
  route('GET', $path, $cb);
}

/**
 * Utility for mapping $cb (callable) for POST requests on
 * $path.
 *
 * @param string $path route to create a handler for
 * @param callable $cb handler to map against POSTs on $path
 *
 * @return void
 */
function post($path, $cb) {
  route('POST', $path, $cb);
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

  static $x = array();

  $f = (config('cookies.flash') ? config('cookies.flash') : '_F');

  if ($c = get_cookie($f))
    $c = json_decode($c, true);
  else
    $c = array();

  if ($msg == null) {

    if (isset($c[$key])) {
      $x[$key] = $c[$key];
      unset($c[$key]);
      set_cookie($f, json_encode($c));
    }

    return (isset($x[$key]) ? $x[$key] : null);
  }

  if (!$now) {
    $c[$key] = $msg;
    set_cookie($f, json_encode($c));
  }

  $x[$key] = $msg;

  return $msg;
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
  $method = ($method ? $method : method());
  $path = ($path ? $path : $_SERVER['REQUEST_URI']);

  // remove the site base url
  if (config('site.url') !== null)
    $path = preg_replace('@^'.preg_quote(site_path()).'@', '', $path);

  // if we have rewriting disabled, remove the first segment or go to '/'
  if (config('rewrite.enable') == false)
    $path = preg_replace('@^\/[^\/]+@', '', $path);

  // clean up the route
  $parts = preg_split('/\?/', $path, -1, PREG_SPLIT_NO_EMPTY);
  $uri = count($parts) ? trim($parts[0], '/') : '';

  // match it
  route($method, "/{$uri}");
}
?>
