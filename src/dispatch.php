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
    $error_callbacks[$code] = $callback;
    return;
  }

  // see if passed callback is a message (string)
  $message = (is_string($callback) ? $callback : 'Page Error');

  // set the response code
  header(
    "{$_SERVER['SERVER_PROTOCOL']} {$code} {$message}",
    true,
    (int) $code
  );

  // if we got callbacks, try to invoke
  if (isset($error_callbacks[$code])) {
    call_user_func($error_callbacks[$code], $code);
    $message = '';
  } else {
    // set default exit message
    $message = "{$code} {$message}";
  }

  exit ($message);

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

  static $config = array();

  // if key is source, load ini file and return
  if ($key === 'source') {
    if (!file_exists($value)) {
      trigger_error(
        "File passed to config('source') not found",
        E_USER_ERROR
      );
    }
    $config = array_replace_recursive($config, parse_ini_file($value, true));
    return;
  }

  // reset configuration to default
  if ($key === null){
    $config = array();
    return;
  }

  // for all other string keys, set or get
  if (is_string($key)) {
    if ($value === null)
      return (isset($config[$key]) ? $config[$key] : null);
    return ($config[$key] = $value);
  }

  // setting multiple settings. merge together if $key is array.
  if (is_array($key)) {
    $keys = array_filter(array_keys($key), 'is_string');
    $keys = array_intersect_key($key, array_flip($keys));
    $config = array_replace_recursive($config, $keys);
  }
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

  // initialize these things only once per request
  static $token = null;
  static $store = null;
  static $cache = array();

  if (!$token) {
    $token = config('dispatch.flash_cookie');
    $token = (!$token ? '_F' : $token);
  }

  // get messages from cookie, if any, or from new hash
  if (!$store) {
    if ($store = cookie($token))
      $store = json_decode($store, true);
    else
      $store = array();
  }

  // if this is a fetch request
  if ($msg == null) {

    // cache value, unset from cookie
    if (isset($store[$key])) {
      $cache[$key] = $store[$key];
      unset($store[$key]);
      cookie($token, json_encode($store));
    }

    // value can now be taken from the cache
    return (isset($cache[$key]) ? $cache[$key] : null);
  }

  // cache it and put it in the cookie
  $store[$key] = $cache[$key] = $msg;

  // rewrite cookie unless now-type
  if (!$now)
    cookie($token, json_encode($store));

  // return the new message
  return ($cache[$key] = $msg);
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
 * Convenience wrapper for htmlentities().
 *
 * @param string $str string to encode
 * @param string $enc encoding to use.
 * @param string $flags htmlentities() flags
 *
 * @return string encoded string
 */
function html($str, $flags = -1, $enc = 'UTF-8', $denc = true) {
  $flags = ($flags < 0 ? ENT_QUOTES : $flags);
  return htmlentities($str, $flags, $enc, $denc);
}

/**
 * Helper for getting values from $_GET, $_POST and route
 * symbols.
 *
 * @param string $name optional. parameter to get the value for
 * @param mixed $default optional. default value for param
 *
 * @return mixed param value.
 */
function params($name = null, $default = null) {

  static $source = null;

  // initialize source if this is the first call
  if (!$source) {
    $source = array_merge($_GET, $_POST);
    if (get_magic_quotes_gpc())
      array_walk_recursive(
        $source,
        function (&$v) { $v = stripslashes($v); }
      );
  }

  // this is a value fetch call
  if (is_string($name))
    return (isset($source[$name]) ? $source[$name] : $default);

  // used by on() for merging in route symbols.
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

    if (($current = ini_get('session.use_trans_sid')) === false) {
      trigger_error(
        'Call to session() requires that sessions be enabled in PHP',
        E_USER_ERROR
      );
    }

    $test = "mix{$current}{$current}";

    $prev = @ini_set('session.use_trans_sid', $test);
    $peek = @ini_set('session.use_trans_sid', $current);

    if ($peek !== $current && $peek !== false)
      session_start();

    $session_active = true;
  }

  if (func_num_args() === 1)
    return (isset($_SESSION[$name]) ? $_SESSION[$name] : null);

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

  static $quoted = -1;

  if ($quoted < 0)
    $quoted = get_magic_quotes_gpc();

  if (func_num_args() === 1) {
    return (
      isset($_COOKIE[$name]) ? (
        $quoted ?
        stripslashes($_COOKIE[$name]) :
        $_COOKIE[$name]
      ) : null
    );
  }

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
    // if we're not on apache
    $headers = array();
    foreach ($_SERVER as $k => $v)
      if (substr($k, 0, 5) == 'HTTP_')
        $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
  }

  // header fetch
  if ($key !== null) {
    $key = strtolower($key);
    return isset($headers[$key]) ? $headers[$key] : null;
  }

  return $headers;
}

/**
 * Convenience function for a quick check if the headers indicate a XHR (AJAX) request.
 * Basically checks the `x-requested-with` header.
 *
 * @return bool true if the current request is an XHR request, false otherwise
 */
function is_xhr() {

  static $is_xhr = null;

  if($is_xhr === null) {
    if(strtolower(request_headers('x-requested-with')) === 'xmlhttprequest') {
      $is_xhr = true;
    } else {
      $is_xhr = false;
    }
  }

  return $is_xhr;
}

/**
 * Convenience function for reading in the request body. JSON
 * and form-urlencoded content are automatically parsed and returned
 * as arrays.
 *
 * @param boolean $load if false, you get a temp file path with the data
 *
 * @return mixed raw string or decoded JSON object
 */
function request_body($load = true) {

  static $content = null;

  // called before, just return the value
  if ($content)
    return $content;

  // get correct content-type of body (hopefully)
  $content_type = isset($_SERVER['HTTP_CONTENT_TYPE']) ?
    $_SERVER['HTTP_CONTENT_TYPE'] :
    $_SERVER['CONTENT_TYPE'];

  // try to load everything
  if ($load) {

    $content = file_get_contents('php://input');
    $content_type = preg_split('/ ?; ?/', $content_type);

    // if json, cache the decoded value
    if ($content_type[0] == 'application/json')
      $content = json_decode($content, true);
    else if ($content_type[0] == 'application/x-www-form-urlencoded')
      parse_str($content, $content);

    return $content;
  }

  // create a temp file with the data
  $path = tempnam(sys_get_temp_dir(), 'disp-');
  $temp = fopen($path, 'w');
  $data = fopen('php://input', 'r');

  // 8k per read
  while ($buff = fread($data, 8192))
    fwrite($temp, $buff);

  fclose($temp);
  fclose($data);

  return $path;
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
function send($path, $filename, $sec_expires = 0) {

  $mime = 'application/octet-stream';
  $etag = md5($path);
  $lmod = filemtime($path);
  $size = filesize($path);

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

  // no time limit, clear buffers
  set_time_limit(0);
  ob_clean();

  // dump the file
  $fp = fopen($path, 'rb');
  while (!feof($fp)) {
    echo fread($fp, 1024*8);
    ob_flush();
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
function files($name) {

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
 * returned instead (or null if nothing mapped).
 *
 * If $name is null all the store will be cleared.
 *
 * @param string $name name of variable to store.
 * @param mixed $value optional, value to store against $name
 *
 * @return mixed value mapped to $name
 */
function scope($name = null, $value = null) {

  static $stash = array();

  if (is_string($name) && $value === null)
    return isset($stash[$name]) ? $stash[$name] : null;

  // if no $name clear $stash
  if (is_null($name)) {
    $stash = array();
    return;
  }

  // set new $value
  if (is_string($name))
    return ($stash[$name] = $value);
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
 * Returns the contents of the template $view, using
 * $locals (optional).
 *
 * @param string $view path to partial
 * @param array $locals optional, hash to load as scope variables
 *
 * @return string content of the partial.
 */
function template($view, $locals = null) {

  if (($view_root = config('dispatch.views')) == null)
    trigger_error("config('dispatch.views') is not set.", E_USER_ERROR);

  extract((array) $locals, EXTR_SKIP);

  $view = $view_root.DIRECTORY_SEPARATOR.$view.'.html.php';
  $html = '';

  if (file_exists($view)) {
    ob_start();
    require $view;
    $html = ob_get_clean();
  } else {
    trigger_error("Template [{$view}] not found.", E_USER_ERROR);
  }

  return $html;
}

/**
 * Returns the contents of the partial $view, using $locals (optional).
 * Partials differ from templates in that their filenames start with _.
 *
 * @param string $view path to partial
 * @param array $locals optional, hash to load as scope variables
 *
 * @return string content of the partial.
 */
function partial($view, $locals = null) {
  $path = basename($view);
  $view = preg_replace('/'.$path.'$/', "_{$path}", $view);
  return template($view, $locals);
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
function render($view, $locals = array(), $layout = null) {

  // load the template and plug it into content()
  $content = template($view, $locals);
  content(trim($content));

  // if we're to use a layout
  if ($layout !== false) {

    // layout = null means use the default
    if ($layout == null) {
      $layout = config('dispatch.layout');
      $layout = ($layout == null) ? 'layout' : $layout;
    }

    // load the layout template, with content() already populated
    return (print template($layout, $locals));
  }

  // no layout was to be used (layout = false)
  echo $content;
}

/**
 * Convenience wrapper for creating route handlers
 * that show nothing but a view.
 *
 * @param string $file name of the view to render
 * @param array|callable $locals locals array or callable that return locals
 * @param string|boolean $layout layout file to use
 *
 * @return callable handler function
 */
function inline($file, $locals = array(), $layout = 'layout') {
  $locals = is_callable($locals) ? $locals() : $locals;
  return function () use ($file, $locals, $layout) {
    render($file, $locals, $layout);
  };
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
function json($obj, $func = null) {
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

  static $symfilters = array();

  // this is a mapping call
  if (is_callable($callback)) {
    $symfilters[$symbol][] = $callback;
    return;
  }

  // run symbol filters
  foreach ($symbol as $sym => $val) {
    if (isset($symfilters[$sym])) {
      foreach ($symfilters[$sym] as $callback) {
        call_user_func($callback, $val);
      }
    }
  }
}

/**
 * Filters parameters for certain symbols that are passed to the request
 * callback. Only one callback can be bound to a symbol. The original request
 * parameter can be accessed using the param() function.
 *
 * @param string $symbol symbol to bind a callback to
 * @param callable|mixed callback to bind to that symbol
 *
 * @return mixed transformed value based on the param
 */
function bind($symbol, $callback = null) {

  // callback store and symbol cache
  static $bindings = array();
  static $symcache = array();

  // Bind a callback to the symbol
  if (is_callable($callback)) {
    $bindings[$symbol] = $callback;
    return;
  }

  // If the symbol is given but is not an array - see if we have filtered it
  if (!is_array($symbol))
    return isset($symcache[$symbol]) ? $symcache[$symbol] : null;

  // If callbacks are bound to symbols, apply them
  $values = array();
  foreach ($symbol as $sym => $val) {
    if (isset($bindings[$sym]))
      $symcache[$sym] = $val = call_user_func($bindings[$sym], $val);
    $values[$sym] = $val;
  }

  return $values;
}

/**
 * Function for mapping callbacks to be invoked before each request.
 * If called with two args, with first being regex, callback is only
 * invoked if the regex matches the request URI.
 *
 * @param callable|string $callback_or_regex callable or regex
 * @param callable $callback required if arg 1 is regex
 *
 * @return void
 */
function before() {

  static $regexp_callbacks = array();
  static $before_callbacks = array();

  $args = func_get_args();
  $func = array_pop($args);
  $rexp = array_pop($args);

  // mapping call
  if (is_callable($func)) {
    if ($rexp)
      $regexp_callbacks[$rexp] = $func;
    else
      $before_callbacks[] = $func;
    return;
  }

  // remap args for clarity
  $verb = $rexp;
  $path = substr($func, 1);

  // let's run regexp callbacks first
  foreach ($regexp_callbacks as $rexp => $func)
    if (preg_match($rexp, $path))
      $func($verb, $path);

  // call generic callbacks
  foreach ($before_callbacks as $func)
    $func($verb, $path);
}

/**
 * Function for mapping callbacks to be invoked after each request.
 * If called with two args, with first being regex, callback is only
 * invoked if the regex matches the request URI.
 *
 * @param callable|string $callback_or_regex callable or regex
 * @param callable $callback required if arg 1 is regex
 *
 * @return void
 */
function after($method_or_cb = null, $path = null) {

  static $regexp_callbacks = array();
  static $after_callbacks = array();

  $args = func_get_args();
  $func = array_pop($args);
  $rexp = array_pop($args);

  // mapping call
  if (is_callable($func)) {
    if ($rexp)
      $regexp_callbacks[$rexp] = $func;
    else
      $after_callbacks[] = $func;
    return;
  }

  // remap args for clarity
  $verb = $rexp;
  $path = $func;

  // let's run regexp callbacks first
  foreach ($regexp_callbacks as $rexp => $func)
    if (preg_match($rexp, $path))
      $func($verb, $path);

  // call generic callbacks
  foreach ($after_callbacks as $func)
    $func($verb, $path);
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
    trigger_error("Invalid call to prefix()", E_USER_ERROR);

  // push, routine, pop so we can nest
  array_push($paths, trim($name, '/'));
  call_user_func($cb);
  array_pop($paths);
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

  // state (routes and cache util)
  static $routes = array();

  $regexp = null;
  $path = trim($path, '/');

  // a callback was passed, so we create a route definition
  if (is_callable($callback)) {

    // if we're inside a resouce, use the path
    if (strlen($pref = prefix()))
      $path = trim("{$pref}/{$path}", '/');

    // add bracketed optional sections and "match anything"
    $path = str_replace(
      array(')', '*'),
      array(')?', '.*?'),
      $path
    );

    // revised regex that allows named capture groups with optional regexes
    // (uses @ to separate param name and regex)
    $regexp = preg_replace_callback(
      '#:([\w]+)(@([^/\(\)]*))?#',
      function ($matches) {
        // 2 versions of named capture groups:
        // with and without a following regex.
        if (isset($matches[3]))
          return '(?P<'.$matches[1].'>'.$matches[3].')';
        else
          return '(?P<'.$matches[1].'>[^/]+)';
      },
      $path
    );

    $method = array_map('strtoupper', (array) $method);
    foreach ($method as $m)
      $routes[$m]['@^'.$regexp.'$@'] = $callback;

    return;
  }

  // setup method and rexp for dispatch
  $method = strtoupper($method);

  // cache miss, do a lookup
  $finder = function ($routes, $path) {
    $found = false;
    foreach ($routes as $regexp => $callback) {
      if (preg_match($regexp, $path, $values))
        return array($regexp, $callback, $values);
    }
    return array(null, null, null);
  };

  // lookup a matching route
  if (isset($routes[$method]))
    list($regexp, $callback, $values) = $finder($routes[$method], $path);

  // if no match, try the any-method handlers
  if (!$regexp && isset($routes['*']))
    list($regexp, $callback, $values) = $finder($routes['*'], $path);

  // we got a match
  if ($regexp) {

    // construct the params for the callback
    $tokens = array_filter(array_keys($values), 'is_string');
    $values = array_map('urldecode', array_intersect_key(
      $values,
      array_flip($tokens)
    ));

    // setup + dispatch
    ob_start();
    params($values);
    filter($values);
    before($method, "@{$path}");

    // adjust $values array to suit the number of args that the callback is expecting.
    // null padding is added to the array to stop error if optional args don't match
    // the number of parameters.
    $ref = new ReflectionFunction($callback);
    $num_args_expected = $ref->getNumberOfParameters();

    // append filler array. (note: can't call array_fill with zero quantity - throws error)
    $values += (($diff = $num_args_expected - count($values)) > 0) ? array_fill(0, $diff, null) : array();

    call_user_func_array($callback, array_values(bind($values)));
    after($method, $path);
    $buff = ob_get_clean();

    if ($method !== 'HEAD')
      echo $buff;

  } else {
    // nothing, so just 404
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
function dispatch() {

  // see if we were invoked with params
  $method = strtoupper($_SERVER['REQUEST_METHOD']);
  if ($method == 'POST') {
    if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']))
      $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
    else
      $method = params('_method') ? params('_method') : $method;
  }

  // get the request_uri basename
  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

  // remove dir path if we live in a subdir
  if ($base = config('dispatch.url')) {
    $base = rtrim(parse_url($base, PHP_URL_PATH), '/');
    $path = preg_replace('@^'.preg_quote($base).'@', '', $path);
  }
  else {
    // improved base directory detection if no config specified
    $base = rtrim(strtr(dirname($_SERVER['SCRIPT_NAME']), '\\', '/'), '/');
    $path = preg_replace('@^'.preg_quote($base).'@', '', $path);
  }

  // remove router file from URI
  if ($stub = config('dispatch.router')) {
    $stub = config('dispatch.router');
    $path = preg_replace('@^/?'.preg_quote(trim($stub, '/')).'@i', '', $path);
  }

  // dispatch it
  on($method, $path);
}
