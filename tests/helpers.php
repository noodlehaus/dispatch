<?php
/*--------------
 * test helpers
 */
function start_http($host, $port, $router) {

  $cmd = 'php -S '.$host.':'.$port.' -t . '.$router.' >/dev/null 2>&1 & echo $!';
  $out = [];

  exec($cmd, $out);
  $pid = (int) $out[0];

  register_shutdown_function(function() use ($pid) {
    exec('kill '.$pid);
  });

  sleep(1);
}

function curly($method, $url, $data = [], $opts = []) {

  $defs = [
    CURLOPT_URL => $url,
    CURLOPT_HEADER => 1,
    CURLOPT_FRESH_CONNECT => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_FORBID_REUSE => 1,
    CURLOPT_COOKIEJAR => 'cookiejar.txt',
    CURLOPT_COOKIEFILE => 'cookiejar.txt',
    CURLOPT_TIMEOUT => 4,
    CURLOPT_FRESH_CONNECT => 1
  ] + $opts;

  if (in_array($method, ['POST', 'DELETE', 'PUT', 'HEAD'])) {
    if ($method === 'POST') {
      $defs[CURLOPT_POST] = true;
    } else {
      $defs[CURLOPT_CUSTOMREQUEST] = $method;
      $data = http_build_query($data);
      if ($method === 'PUT')
        $defs[CURLOPT_HTTPHEADER] = ['Content-Length: '.strlen($data)];
    }
    $defs[CURLOPT_POSTFIELDS] = $data;
  }

  $ch = curl_init();
  curl_setopt_array($ch, $defs);

  if (!($res = curl_exec($ch)))
    trigger_error(curl_error($ch));

  curl_close($ch);

  return $res;
}

function test_count($total_inc = 0, $failed_inc = 0) {
  static $total = 0, $failed = 0;
  $total += $total_inc;
  $failed += $failed_inc;
  return [$total, $failed];
}

function test_summary() {
  list($total, $failed) = test_count();
  echo "--".PHP_EOL;
  echo "{$total} tests, {$failed} failed".PHP_EOL;
}

function test_stack($name = null) {
  static $stack = [];
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
