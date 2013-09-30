<?php
/**
 * @author Jesus A. Domingo
 * @license MIT
 */

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

  static $error_callbacks = array();

  $code = (string) $code;

  // this is a hook setup, save and return
  if (is_callable($callback)) {
    $error_callbacks[$code][] = $callback;
    return;
  }

  // error trigger path, set headers, call hooks
  $message = (is_string($callback) ? $callback : 'Page Error');

  if (PHP_SAPI !== 'cli')
    header(
      "{$_SERVER['SERVER_PROTOCOL']} {$code} {$message}",
      true,
      (int) $code
    );

  if (isset($error_callbacks[$code]))
    foreach ($error_callbacks[$code] as $cb)
      call_user_func($cb, $code);
  else
    echo "{$code} {$message}\n";

  exit;
}

/**
 * Sets or gets an entry from the loaded config.ini file. If the $key passed
 * is 'source', it expects $value to be a path to an ini file to load. Calls
 * to config('source', 'inifile.ini') will aggregate the contents of the ini
 * file into config().
 *
 * @param string $key setting to set or get. passing null resets the config
 * @param string $value optional, If present, sets $key to this $value.
 *
 * @return mixed|null value
 */
function config($key = null, $value = null) {

  static $_config = array();

  // forced reset call
  if ($key === null) {
    $_config = array();
    return;
  }

  // if key is source, load ini file and return
  if ($key === 'source') {
    !file_exists($value) and
      error(500, "File passed to config('source') not found");
    $_config = array_merge($_config, parse_ini_file($value, true));
    return;
  }

  // for all other string keys, set or get
  if (is_string($key)) {
    if ($value === null)
      return (isset($_config[$key]) ? $_config[$key] : null);
    return ($_config[$key] = $value);
  }

  // setting multiple settings
  if (is_array($key) && array_diff_key($key, array_keys(array_keys($key))))
    $_config = array_merge($_config, $key);
}

/**
 * Returns the string contained by 'dispatch.url' in config.ini.
 * This includes the hostname and path. If called with $path_only set to
 * true, it will return only the path section of the URL.
 *
 * @param boolean $path_only defaults to false, true returns only the path
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

  static $x = array();

  $f = config('dispatch.flash_cookie');
  $f = (!$f ? '_F' : $f);

  if ($c = cookie($f))
    $c = json_decode($c, true);
  else
    $c = array();

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
function url($str) {
  return urlencode($str);
}

/**
 * BC - marked for deprecation
 */
function u($str) {
  trigger_error(
    "The function u() has been marked for deprecation. ".
    "Please use url() instead",
    E_USER_DEPRECATED
  );
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
function html($str, $flags = -1, $enc = 'UTF-8', $denc = true) {
  $flags = ($flags < 0 ? ENT_COMPAT|ENT_HTML401 : $flags);
  return htmlentities($str, $flags, $enc, $denc);
}

/**
 * BC - marked for deprecation
 */
function h($str, $flags = -1, $enc = 'UTF-8', $denc = true) {
  trigger_error(
    "The function h() has been marked for deprecation. ".
    "Please use html() instead",
    E_USER_DEPRECATED
  );
  return html($str, $flags, $enc, $denc);
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

  if ($name == null)
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

  static $session_active = false;

  // stackoverflow.com: 3788369
  if ($session_active === false) {

    if (($current = ini_get('session.use_trans_sid')) === false)
      error(
        500,
        'Calls to session() requires [session.use_trans_sid] to be set'
      );

    $test = "mix{$current}{$current}";

    $prev = @ini_set('session.use_trans_sid', $test);
    $peek = @ini_set('session.use_trans_sid', $current);

    if ($peek !== $current && $peek !== false)
      session_start();

    $session_active = true;
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
 * Convenience wrapper for accessing http request headers.
 *
 * @param string $key name of http request header to fetch
 *
 * @return string value for the header, or null if header isn't there.
 */
function request_headers($key = null) {

  static $headers = null;

  // if first call, pull headers
  if (!$headers) {
    if (function_exists('getallheaders')) {
      foreach (getallheaders() as $k => $v)
        $headers[strtolower($k)] = $v;
    } else {
      // if we're not on apache
      $headers = array();
      foreach ($_SERVER as $k => $v)
        if (substr($k, 0, 5) == 'HTTP_')
          $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
    }
  }

  if ($key == null)
    return $headers;

  $key = strtolower($key);

  return (isset($headers[$key]) ? $headers[$key] : null);
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
  $content_type = preg_split('/ ?; ?/', $content_type);

  if ($content_type[0] == 'application/json')
    $content = json_decode($content);
  else if ($content_type[0] == 'application/x-www-form-urlencoded')
    parse_str($content, $content);

  return $content;
}

/**
 * Creates a file download response for the specified path using the passed
 * filename. If $sec_expires is specified, this duration will be used
 * to specify the download's cache expiration header.
 *
 * @param string $path full path to the file to stream
 * @param string $filename filename to use in the content-disposition header
 * @param int $sec_expires optional, defaults to 0. in seconds.
 *
 * @return void
 */
function download($path, $filename, $sec_expires = 0) {

  ($finf = finfo_open(FILEINFO_MIME)) or
    error(500, "download() failed to open fileinfo database");

  // get meta info
  $mime = finfo_file($finf, $path);
  $etag = md5($path);
  $lmod = filemtime($path);
  $size = filesize($path);

  // close finfo
  finfo_close($finf);

  // cache headers
  header('Pragma: public');
  header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lmod).' GMT');
  header('ETag: '.$etag);

  // if we want this to persist
  if ($sec_expires > 0) {
    header('Cache-Control: maxage='.$sec_expires);
    header(
      'Expires: '.gmdate('D, d M Y H:i:s',
      time() + $sec_expires).' GMT'
    );
  }

  // file info
  header('Content-Disposition: attachment; filename='.urlencode($filename));
  header('Content-Type: '.$mime);
  header('Content-Length: '.$size);

  // try to read and flush
  while (!feof($fp = fopen($path, 'r'))) {
    echo fread($fp, 65536);
    flush();
  }

  fclose($fp);
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

    $result = array();

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

  static $_stash = array();

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

  $client = request_headers('client-ip');
  $client = $client ?: request_headers('x-forwarded-for');
  $client = $client ?: $_SERVER['REMOTE_ADDR'];

  return $client;
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
 * @param array $locals optional, hash to load as scope variables
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
 * @param string|bool path to the layout file to use, false means no layout
 *
 * @return string contents of the view + layout
 */
function render($view, $locals = null, $layout = null) {

  $view_root = config('dispatch.views');
  $view_root = (!$view_root ? 'layout' : $view_root);

  if (is_array($locals) && count($locals))
    extract($locals, EXTR_SKIP);

  ob_start();
  require $view_root.DIRECTORY_SEPARATOR.$view.'.html.php';
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

  static $filter_callbacks = array();

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
function before($method_or_cb = null, $path = null) {

  static $before_callbacks = array();

  if (!is_callable($method_or_cb)) {
    foreach ($before_callbacks as $callback)
      call_user_func_array($callback, array($method_or_cb, $path));
  } else {
    $before_callbacks[] = $method_or_cb;
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
function after($method_or_cb = null, $path = null) {

  static $after_callbacks = array();

  if (!is_callable($method_or_cb)) {
    foreach ($after_callbacks as $callback)
      call_user_func_array($callback, array($method_or_cb, $path));
  } else {
    $after_callbacks[] = $method_or_cb;
  }
}

/**
 * Group all routes created within $cb under the $name prefix.
 *
 * @param string $name required. string to prepend to routes created in $cb
 * @param callable $cb required. function containing route calls
 *
 * @return void
 */
function prefix($name = null, $cb = null) {

  static $paths = array();

  // this is used by the system to get the current route
  if (($nargs = func_num_args()) == 0)
    return implode('/', $paths);

  // outside of sys calls, always require 2 params
  if ($nargs < 2)
    error(500, 'Invalid call to prefix()');

  // push, routine, pop so we can nest
  array_push($paths, trim($name, '/'));
  call_user_func($cb);
  array_pop($paths);
}

/**
 * BC for prefix()
 */
function resource($name = null, $cb = null) {
  trigger_error(
    "The function resource() has been marked for deprecation. ".
    "Please use prefix() instead",
    E_USER_DEPRECATED
  );
  prefix($name, $cb);
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
  static $routes = array(
    'HEAD' => array(),
    'GET' => array(),
    'POST' => array(),
    'PUT' => array(),
    'PATCH' => array(),
    'DELETE' => array()
  );

  // we don't want slashes on ends
  $path = trim($path, '/');

  // a callback was passed, so we create a route definition
  if (is_callable($callback)) {

    // if we're inside a resouce, use the path
    if (strlen($pref = prefix()))
      $path = trim("{$pref}/{$path}", '/');

    // create the regex for this route
    $regex = preg_replace('@:(\w+)@', '(?<\1>[^/]+)', $path);

    // create the list of methods to map to
    $method = (array) $method;

    // wildcard method means for all supported methods
    if (!in_array('*', $method)) {
      array_walk($method, function (&$m) { $m = strtoupper($m); });
      $method = array_intersect(array_keys($routes), $method);
    } else {
      $method = array_keys($routes);
    }

    // create a route entry for this path on every method
    foreach ($method as $m)
      $routes[$m][$path] = array(
        'regex' => '@^'.$regex.'$@',
        'callback' => $callback
      );

  } else {

    // not a string? invalid
    if (!is_string($method))
      error(400, 'Invalid method');

    // then normalize
    $method = strtoupper($method);

    // for invokation, only support strings
    if (!in_array($method, array_keys($routes)))
      error(400, 'Method not supported');

    // call our before filters
    before($method, $path);

    // flag for 404 check
    $found = false;

    // callback is null, so this is a route invokation. look up the callback.
    foreach ($routes[$method] as $pattern => $info) {

      // skip non-matching routes
      if (!preg_match($info['regex'], $path, $values))
        continue;

      // construct the params for the callback
      array_shift($values);
      preg_match_all('@:([\w]+)@', $pattern, $symbols);
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

      // shouldn't do a 404 after the break
      $found = true;

      break;
    }

    // call our after filters
    after($method, $path);

    if (!$found)
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

  // check for override
  $override = request_headers('x-http-method-override');
  $override = $override ? $override : params('_method');

  // set correct method
  $method = (
    $override ?
    $override :
    ($method ? $method : $_SERVER['REQUEST_METHOD'])
  );

  // dispatch it
  on($method, $path);
}
?>
