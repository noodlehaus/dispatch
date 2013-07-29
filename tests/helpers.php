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

function do_post($url, array $post = [], array $options = []) {

  $defaults = array(
    CURLOPT_POST => 1,
    CURLOPT_HEADER => 1,
    CURLOPT_URL => $url,
    CURLOPT_FRESH_CONNECT => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_FORBID_REUSE => 1,
    CURLOPT_COOKIEJAR => 'cookiejar.txt',
    CURLOPT_COOKIEFILE => 'cookiejar.txt',
    CURLOPT_TIMEOUT => 4,
    CURLOPT_POSTFIELDS => http_build_query($post)
  );

  $ch = curl_init();
  curl_setopt_array($ch, ($options + $defaults));
  if (!$result = curl_exec($ch)) {
    trigger_error(curl_error($ch));
  }
  curl_close($ch);
  return $result;
}

function do_get($url, array $get = [], array $options = []) {

  $defaults = array(
    CURLOPT_URL => $url.(strpos($url, '?') === FALSE ? '?' : '').http_build_query($get),
    CURLOPT_HEADER => 1,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_COOKIEJAR => 'cookiejar.txt',
    CURLOPT_COOKIEFILE => 'cookiejar.txt',
    CURLOPT_TIMEOUT => 4
  );

  $ch = curl_init();
  curl_setopt_array($ch, ($options + $defaults));
  if (!$result = curl_exec($ch)) {
    trigger_error(curl_error($ch));
  }
  curl_close($ch);
  return $result;
}

function test($title, $cb) {
  echo "- {$title}".PHP_EOL;
  call_user_func($cb);
}
?>
