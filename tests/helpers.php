<?php
/*--------------
 * test helpers
 */
function start_http($host, $port, $router) {

  $dir = dirname($router);
  $cmd = 'php -S '.$host.':'.$port.' -t '.$dir.' '.$router.' >/dev/null 2>&1 & echo $!';
  $out = array();

  exec($cmd, $out);
  $pid = (int) $out[0];

  register_shutdown_function(function() use ($pid) {
    exec('kill '.$pid);
  });

  sleep(1);
}

function curl($method, $url, $data = array(), $opts = array()) {

  $opts += array(
    CURLOPT_URL => $url,
    CURLOPT_HEADER => 1,
    CURLOPT_FRESH_CONNECT => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_FORBID_REUSE => 1,
    CURLOPT_COOKIEJAR => __DIR__.'/cookiejar.txt',
    CURLOPT_COOKIEFILE => __DIR__.'/cookiejar.txt',
    CURLOPT_TIMEOUT => 4,
    CURLOPT_FRESH_CONNECT => 1
  );

  if (!isset($opts[CURLOPT_HTTPHEADER]))
    $opts[CURLOPT_HTTPHEADER] = array();

  if (in_array($method, array('POST', 'DELETE', 'PUT', 'HEAD'))) {
    if ($method === 'POST') {
      $opts[CURLOPT_POST] = true;
    } else {
      $opts[CURLOPT_CUSTOMREQUEST] = $method;
      $data = http_build_query($data);
      if ($method === 'PUT')
        $opts[CURLOPT_HTTPHEADER][] = 'Content-Length: '.strlen($data);
    }
    $opts[CURLOPT_POSTFIELDS] = $data;
  }

  $ch = curl_init();
  curl_setopt_array($ch, $opts);

  if (!($res = curl_exec($ch)))
    trigger_error(curl_error($ch));

  curl_close($ch);

  return $res;
}

function test_count($total_inc = 0, $failed_inc = 0) {
  static $total = 0, $failed = 0;
  $total += $total_inc;
  $failed += $failed_inc;
  return array($total, $failed);
}

function test_summary() {
  list($total, $failed) = test_count();
  echo "--".PHP_EOL;
  echo "{$total} tests, {$failed} failed".PHP_EOL;
}

function test_stack($name = null) {
  static $stack = array();
  if (!$name)
    return array_pop($stack);
  array_push($stack, $name);
}

function test($title, $cb) {
  test_stack($title);
  test_count(1, 0);
  try {
    call_user_func($cb);
    echo "\e[1;32mPASSED\e[0m: {$title}".PHP_EOL;
  } catch (Exception $ex) {}
}

assert_options(ASSERT_BAIL, 0);
assert_options(ASSERT_WARNING, 1);
assert_options(ASSERT_QUIET_EVAL, 0);

assert_options(ASSERT_CALLBACK, function ($script, $line, $message) {
  $task = test_stack();
  echo "\e[1;31m";
  echo "FAILED: {$task}".PHP_EOL;
  echo "\e[0m";
  test_count(0, 1);
  throw new Exception();
});
?>
