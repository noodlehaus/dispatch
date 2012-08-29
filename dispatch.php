<?php
/**
 * dispatch php 5.3 utility library
 *
 * Jesus A. Domingo
 * http://noodlehaus.github.com/dispatch
 **/

function error($code, $message) {
  if (PHP_SAPI === 'cli')
    die("Error {$code}: {$message}\n");
  else
    http_error($code, $message);
}

function http_error($code = 500, $message = "Internal server error") {
  @header("HTTP/1.0 {$code} {$message}", true, $code);
  die($message);
}

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
  error(500, 'dispatch requires at least PHP 5.3 to run.');
}

class PassException extends Exception {}

function config($key, $value = null) {

  static $_config = array();

  if ($key === 'source' && file_exists($value))
    $_config = parse_ini_file($value, true);
  else if ($value == null)
    return (isset($_config[$key]) ? $_config[$key] : null);

  $_config[$key] = $value;
}

function to_b64($str) {
  $str = base64_encode($str);
  $str = preg_replace('/\//', '_', $str);
  $str = preg_replace('/\+/', '.', $str);
  $str = preg_replace('/\=/', '-', $str);
  return trim($str, '-');
}

function from_b64($str) {
  $str = preg_replace('/\_/', '/', $str);
  $str = preg_replace('/\./', '+', $str);
  $str = preg_replace('/\-/', '=', $str);
  $str = base64_decode($str);
  return $str;
}

if (extension_loaded('mcrypt')) {

  function encrypt($decoded) {

    if (($secret = config('secret')) == null)
      error(500, 'encrypt() requires that you define the [secret] setting.');

    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
    $iv_code = mcrypt_create_iv($iv_size, MCRYPT_RAND);

    return to_b64(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $secret, $decoded, MCRYPT_MODE_ECB, $iv_code));
  }

  function decrypt($encoded) {

    if (($secret = config('secret')) == null)
      error(500, 'decrypt() requires that you define the [application.secret] setting.');

    $enc_str = from_b64($encoded);
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
    $iv_code = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $enc_str = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $secret, $enc_str, MCRYPT_MODE_ECB, $iv_code);

    return rtrim($enc_str, "\0");
  }

}

function set_cookie($name, $value, $expire = 31536000, $path = '/') {
  $value = (function_exists('encrypt') ? encrypt($value) : $value);
  setcookie($name, encrypt($value), time() + $expire, $path);
}

function get_cookie($name) {

  $value = from($_COOKIE, $name);

  if ($value)
    $value = (function_exists('decrypt') ? decrypt($value) : $value);

  return $value;
}

function delete_cookie() {
  $cookies = func_get_args();
  foreach ($cookies as $ck)
    setcookie($ck, '', -10, '/');
}

function warn($name = null, $message = null) {

	static $warnings = array();

	if (!$name)
		return count(array_keys($warnings));

	if (!$message)
		return isset($warnings[$name]) ? $warnings[$name] : '';

	$warnings[$name] = $message;
}

function _u($str) {
  return urlencode($str);
}

function _h($str, $enc = 'UTF-8', $flags = ENT_QUOTES) {
  return htmlentities($str, $flags, $enc);
}

function from($source, $name) {
  if (is_array($name)) {
    $data = array();
    foreach ($name as $k)
      $data[$k] = isset($source[$k]) ? $source[$k] : null ;
    return $data;
  }
  return isset($source[$name]) ? $source[$name] : null ;
}

function stash($name, $value = null) {

  static $_stash = array();

  if ($value === null)
    return isset($_stash[$name]) ? $_stash[$name] : null;

  $_stash[$name] = $value;

  return $value;
}

if (PHP_SAPI !== 'cli') {

  function method($verb = null) {

    if ($verb == null || (strtoupper($verb) == strtoupper($_SERVER['REQUEST_METHOD'])))
      return strtoupper($_SERVER['REQUEST_METHOD']);

    error(400, 'Bad request');
  }

  function client_ip() {

    if (isset($_SERVER['HTTP_CLIENT_IP']))
      return $_SERVER['HTTP_CLIENT_IP'];
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
      return $_SERVER['HTTP_X_FORWARDED_FOR'];

    return $_SERVER['REMOTE_ADDR'];
  }

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
          error(500, 'Incorrect call to redirect()');
        $path = $argv[0];
        break;
      default:
        error(500, 'Incorrect call to redirect()');
    }

    $cond = (is_callable($cond) ? !!call_user_func($cond) : !!$cond);

    if (!$cond)
      return;

    header('Location: '.$path, true, $code);
    exit;
  }

}

function partial($view, $locals = null) {

  if (is_array($locals) && count($locals)) {
    extract($locals, EXTR_SKIP);
  }

  $view_root = config('views');
  $view_root = ($view_root == null) ? './views' : $view_root;

  $path = basename($view);
  $view = preg_replace('/'.$path.'$/', "_{$path}", $view);
  $view = "{$view_root}/{$view}.html.php";

  if (file_exists($view)) {
    ob_start();
    require $view;
    return ob_get_clean();
  } else {
    error(500, "Partial [{$view}] not found");
  }

  return '';
}

function content($value = null) {
  return stash('__content__', $value);
}

function render($view, $locals = null, $layout = null) {

  if (is_array($locals) && count($locals)) {
    extract($locals, EXTR_SKIP);
  }

  $view_root = config('views');
  $view_root = ($view_root == null) ? './views' : $view_root;

  ob_start();
  include "{$view_root}/{$view}.html.php";
  content(trim(ob_get_clean()));

  if ($layout !== false) {

    if ($layout == null) {
      $layout = config('layout');
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

function condition() {

  static $cb = array();

  $argv = func_get_args();
  $argc = count($argv);

  if (!$argc)
    error(500, 'Incorrect call to condition()');

  $name = array_shift($argv);
  $argc = $argc - 1;

  if (!$argc && is_callable($cb[$name]))
    return call_user_func($cb[$name]);

  if (is_callable($argv[0]))
    return ($cb[$name] = $argv[0]);

  if (is_callable($cb[$name]))
    return call_user_func_array($cb[$name], $argv);

  error(500, 'condition ['.$name.'] is undefined');
}

function middleware($cb_or_path = null) {

  static $cb_map = array();

  if ($cb_or_path == null || is_string($cb_or_path)) {
    foreach ($cb_map as $cb) {
      call_user_func($cb, $cb_or_path);
    }
  } else {
    array_push($cb_map, $cb_or_path);
  }
}

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

  error(500, 'Call to filter() requires either a symbol + callback or a list of symbols to filter');
}

function route_to_regex($route) {
  $route = preg_replace_callback('@:[\w]+@i', function ($matches) {
    $token = str_replace(':', '', $matches[0]);
    return '(?P<'.$token.'>[a-z0-9_\0-\.]+)';
  }, $route);
  return '@^'.rtrim($route, '/').'$@i';
}

function route($method, $pattern, $callback = null) {

  // callback map by request type
  static $route_map = array(
    'GET' => array(),
    'POST' => array(),
    'PUT' => array(),
    'DELETE' => array()
  );

  $method = strtoupper($method);

  if (in_array($method, array('GET', 'POST', 'PUT', 'DELETE'))) {

    // a callback was passed, so we create a route defiition
    if ($callback !== null) {

      // create a route entry for this pattern
      $route_map[$method][$pattern] = array(
        'expression' => route_to_regex($pattern),
        'callback' => $callback
      );

    } else {

      // callback is null, so this is a route invokation. look up the callback.
      foreach ($route_map[$method] as $pat => $obj) {

        // if the requested uri ($pat) has a matching route, let's invoke the cb
        if (preg_match($obj['expression'], $pattern, $vals)) {

          // call middleware
          middleware($pattern);

          // construct the params for the callback
          array_shift($vals);
          preg_match_all('@:([\w]+)@', $pat, $keys, PREG_PATTERN_ORDER);
          $keys = array_shift($keys);
          $params = array();

          foreach ($keys as $index => $id) {
            $id = substr($id, 1);
            if (isset($vals[$id])) {
              array_push($params, urlencode($vals[$id]));
            }
          }

          // call filters if we have symbols
          if (count($keys)) {
            filter(array_values($keys), $vals);
          }

          // if no call to pass was made, exit after first route
          try {
            if (is_callable($obj['callback'])) {
              call_user_func_array($obj['callback'], $params);
            }
            break;
          } catch (PassException $e) {
            continue;
          }

        }
      }
    }

  } else {
    error(500, "Request method [{$method}] is not supported.");
  }
}

function any($only, $path, $cb) {
  if (is_string($only) && is_callable($path)) {
    route($only, $path);
  } else if (is_array($only) && is_string($path) && is_callable($cb)) {
    foreach ($only as $method) {
      route($method, $cb);
    }
  }
}

function get($path, $cb) {
  route('GET', $path, $cb);
}

function post($path, $cb) {
  route('POST', $path, $cb);
}

function put($path, $cb) {
  route('PUT', $path, $cb);
}

function del($path, $cb) {
  route('DELETE', $path, $cb);
}

function pass() {
  throw new PassException('Jumping to pass handler');
}

function flash($key, $msg = null, $now = false) {

  static $x = array(),
         $f = null;

  $f = (config('f_cookie') ? config('f_cookie') : '_F');

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
}

function dispatch($fake_uri = null, $fake_method = null) {

  if (PHP_SAPI === 'cli' && (!$fake_uri || !$fake_method))
    error(500, 'dispatch() requires a fake uri and method when invoked from the CLI');

  $parts = preg_split('/\?/', ($fake_uri == null ? $_SERVER['REQUEST_URI'] : $fake_uri), -1, PREG_SPLIT_NO_EMPTY);

  $uri = trim($parts[0], '/');
  $uri = (!config('rewrite') ? preg_replace('/^index\.php\/?/', '', $uri) : $uri);
  $uri = strlen($uri) ? $uri : 'index';

  if ($fake_method !== null)
    route($fake_method, "/{$uri}");
  else
    route(method(), "/{$uri}");
}
?>
