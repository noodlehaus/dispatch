<?php
/**
 * @author Jesus A. Domingo
 * @license MIT
 */

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300)
  error(500, 'dispatch requires at least PHP 5.3 to run.');

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
function error($code, $callback_or_text = null) {

  static $error_callbacks = array();

  $code = (string) $code;

  if (is_callable($callback_or_text)) {
    $error_callbacks[$code][] = $callback_or_text;
  } else {

    if (isset($error_callbacks[$code])) {
      @header("HTTP/1.1 {$code} Page Error", true, $code);
      foreach ($error_callbacks[$code] as $callback)
        call_user_func($callback);
      exit;
    }

    if ($callback_or_text && is_string($callback_or_text)) {
      @header("HTTP/1.1 {$code} {$callback_or_text}", true, $code);
      die("{$code} - {$callback_or_text}\n");
    }
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

  static $_config = array();

  if ($key === 'source' && file_exists($value))
    $_config = array_merge($_config, parse_ini_file($value, true));
  else if ($value === null)
    return (isset($_config[$key]) ? $_config[$key] : null);
  else
    return ($_config[$key] = $value);

  return $value;
}

/**
 * Returns the string contained by 'site.url' in config.ini.
 * This includes the hostname and path. If called with $path_only set to
 * true, it will return only the path section of the URL.
 *
 * @param boolean $path_only defaults to false, true means return only the path
 * @return string value pointed to by 'site.url' in config.ini.
 */
function site_url($path_only = false) {

  if (!config('site.url'))
    return null;

  if ($path_only)
    return rtrim(parse_url(config('site.url'), PHP_URL_PATH), '/');

  return rtrim(config('site.url'), '/').'/';
}

if (extension_loaded('mcrypt')) {

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
      error(500, "config('cookies.secret') is not set.");

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
      error(500, "config('cookies.secret') is not set.");

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

/**
 * Function that declares the caching functions depending on the back-end
 * module used.
 *
 * @param string $module either apc or memcached
 *
 * @return void
 */
function cache_enable($module) {

  if ($module != 'apc' && $module != 'memcached')
    error(500, 'cache_enable() only supports apc or memcached');

  if (!extension_loaded('apc') && !extension_loaded('memcached'))
    error(500, 'cache_enable() requires either apc or memcached extensions');

  if ($module === 'memcached') {

    /**
     * Initializer for memcached.
     *
     * @return object Memcached instance
     */
    function _memcached() {

      static $memcached = null;

      if (!$memcached) {

        $connections = config('cache.connection');

        if (!$connections)
          error(500, '[cache.driver] set to memcached but [cache.connection] entries missing');

        // cast it so we just have a generic array handler
        if (!is_array($connections))
          $connections = (array) $connections;

        // our memcached servers
        $servers = array();

        // go through each cache.connection[] entry and require
        // at least a host and port for each one
        foreach ($connections as $str) {
          $parts = explode(':', $str);
          if (count($parts) < 2)
            error(500, '[cache.connection] format is <host>:<port>[:<weight>]');
          $servers[] = $parts;
        }

        $memcached = new Memcached();
        $memcached->addservers($servers);
      }

      return $memcached;
    }

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

      $store = _memcached();
      $value = $store->get($key);

      if ($store->getResultCode() === Memcached::RES_NOTFOUND) {
        $value = call_user_func($func);
        if (!$value !== null)
          $store->set($key, $value, $ttl);
      }

      return $value;
    }

    /**
     * Invalidates a key or list of keys from the cache.
     *
     * @param string $v,... key or keys to invalidate from the cache.
     *
     * @return void
     */
    function cache_invalidate() {
      _memcached()->deleteMulti(func_get_args());
    }

  // apc
  } else {

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
          apc_add($key, $data, $ttl);
      }

      return $data;
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

  $f = config('cookies.flash');

  if (!$f)
    error(500, "config('cookies.flash') is not set.");

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
function _h($str, $flags = ENT_QUOTES, $enc = 'UTF-8') {
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

  if (!is_array($name))
    return isset($source[$name]) ? $source[$name] : $default ;

  $data = array();

  foreach ($name as $k)
    $data[$k] = isset($source[$k]) ? $source[$k] : $default ;

  return $data;
}

/**
 * Function that returns the request body along with content type
 * and content length info in a hash, if they're available.
 *
 * @param callable $parser optional function that will be used to parse the
 *    content. this callback will be sent 3 parameters -- content-type,
 *    content-length and actual content.
 * @return array hash containing content-length, content-type and content
 */
function request_body($parser = null) {

  $content_type = isset($_SERVER['HTTP_CONTENT_TYPE']) ?
    $_SERVER['HTTP_CONTENT_TYPE'] :
    $_SERVER['CONTENT_TYPE'];

  preg_match('@^[^;]+@', $content_type, $content_type);

  $content_raw = file_get_contents('php://input');
  $content_length = strlen($content_raw);

  // if no parser was passed, try to do smart parsing
  if (!is_callable($parser)) {

    if ($content_type[0] == 'application/json')
      $content = json_decode($content_raw);
    else if ($content_type[0] == 'application/x-www-form-urlencoded')
      parse_str($content_raw, $content);

  } else {
    $content = $parser($content_type, $content_length, $content_raw);
  }

  return array(
    'content-length' => $content_length,
    'content-type' => $content_type[0],
    'content-parsed' => $content,
    'content-raw' => $content_raw
  );
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

  if ($verb === null)
    return $_SERVER['REQUEST_METHOD'];

  if (strtoupper($verb) === $_SERVER['REQUEST_METHOD'])
    return true;

  return false;
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

  if (is_array($locals) && count($locals))
    extract($locals, EXTR_SKIP);

  if (($view_root = config('views.root')) == null)
    error(500, "config('views.root') is not set.");

  $path = basename($view);
  $view = preg_replace('/'.$path.'$/', "_{$path}", $view);
  $view = "{$view_root}/{$view}.html.php";

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

  if (($view_root = config('views.root')) == null)
    error(500, "config('views.root') is not set.");

  if (is_array($locals) && count($locals))
    extract($locals, EXTR_SKIP);

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
function before($callback = null) {

  static $before_callbacks = array();

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

  static $after_callbacks = array();

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
function route($method, $path, $callback = null) {

  // callback map by request type
  static $route_map = array();

  // support for 'GET /uri/path' format of routes
  if (is_callable($path)) {
    $callback = $path;
    list($method, $path) = preg_split('@\s+@', $method, 2);
  }

  $method = strtoupper($method);

  if (!in_array($method, array('GET', 'POST', 'PUT', 'DELETE', 'HEAD')))
    error(400, 'Method not supported');

  // a callback was passed, so we create a route defiition
  if ($callback !== null) {

    // normalize slashes
    $path = '/'.trim($path, '/');

    // create the regex from the path
    $regex = preg_replace_callback('@:\w+@', function ($matches) {
      return '(?<'.str_replace(':', '', $matches[0]).'>[a-z0-9-_\.]+)';
    }, $path);

    // create a route entry for this path
    $route_map[$method][$path] = array(
      'regex' => '@^'.$regex.'$@i',
      'callback' => $callback
    );

  } else {

    // do we have a method override?
    if (isset($_REQUEST['_method']))
      $method = strtoupper($_REQUEST['_method']);

    // callback is null, so this is a route invokation. look up the callback.
    foreach ($route_map[$method] as $pattern => $info) {

      // skip non-matching routes
      if (!preg_match($info['regex'], $path, $values))
        continue;

      // construct the params for the callback
      array_shift($values);
      preg_match_all('@:([\w]+)@', $pattern, $symbols, PREG_PATTERN_ORDER);
      $symbols = $symbols[1];
      $values = array_intersect_key($values, array_flip($symbols));

      // call filters if we have symbols
      if (count($symbols))
        filter($values);

      // do before() callbacks
      before();

      // invoke callback
      call_user_func_array($info['callback'], array_values($values));

      // do after() callbacks
      after();

      // done
      return;
    }

    // we got a 404
    error(404, 'Page not found');
  }
}

/**
 * Utility for mapping $cb (callable) to HEAD requests on
 * $path.
 *
 * @param string $path route to create a handler for
 * @param callable $cb handler to map against HEADs on $path
 *
 * @return void
 */
function head($path, $cb) {
  route('HEAD', $path, $cb);
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
function delete($path, $cb) {
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
 * Convenience tool for mounting a class or mapping
 * as a RESTful service.
 *
 * @param string $root path where the resource will be mounted
 * @param object $resource resource instance to mount
 * @param array $actions optional list of actions to publish for the resource
 *
 * @return void
 */
function restify($root, $resource, $actions = null) {

  if (!is_object($resource))
    error(500, 'call to restify() requires a resource instance as 2nd argument');

  $action_map = array(
    'index' => array('GET', '(/(index/?)?)?'),
    'new' => array('GET', '/new/?'),
    'edit' => array('GET', '/:id/edit/?'),
    'show' => array('GET', '/:id(/(show/?)?)?'),
    'create' => array('POST', '(/(create/?)?)?'),
    'update' => array('PUT', '/:id/?'),
    'delete' => array('DELETE', '/:id/?')
  );

  $root = trim($root, '/');

  if ($actions && is_array($actions)) {
    $actions = array_uintersect(
      array_keys($action_map),
      $actions,
      'strcasecmp'
    );
  } else {
    $actions = array_keys($action_map);
  }

  foreach ($actions as $action) {
    route(
      $action_map[$action][0],
      $root.$action_map[$action][1],
      array($resource, 'on'.ucfirst($action))
    );
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
  $method = ($method ? $method : method());

  // normalize routing base, if site is in sub-dir
  $path = ($path ? $path : $_SERVER['REQUEST_URI']);
  $root = config('site.router');
  $base = site_url(true);

  // strip base from path
  if ($base !== null)
    $path = preg_replace('@^'.preg_quote($base).'@', '', $path);

  // if we have a routing file (no mod_rewrite), strip it from the URI
  if ($root)
    $path = preg_replace('@^/?'.preg_quote(trim($root, '/')).'@i', '', $path);

  // get just the route path, minus the query
  $path = parse_url($path, PHP_URL_PATH);

  // match it
  route($method, '/'.trim($path, '/'));
}
?>
