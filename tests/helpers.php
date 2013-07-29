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
    CURLOPT_TIMEOUT => 4
  ];

  if (in_array($method, ['POST', 'DELETE', 'PUT', 'HEAD'])) {
    $defs[CURLOPT_POSTFIELDS] = http_build_query($data);
    if ($method === 'POST')
      $defs[CURLOPT_POST] = 1;
    else
    $defs[CURLOPT_CUSTOMREQUEST] = $method;
  }

  $ch = curl_init();
  curl_setopt_array($ch, $defs);

  if (!($res = curl_exec($ch)))
    trigger_error(curl_error($ch));

  curl_close($ch);

  return $res;
}

function test($title, $cb) {
  echo "- {$title}".PHP_EOL;
  call_user_func($cb);
}
?>
