<?php
require __DIR__.'/dispatch.php';

# handle specific error codes
map([400, 404, 500], function ($code) {

  $code = intval($code);

  switch ($code) {

  case 404:
    echo "Page not found";
    break;

  case 400:
    echo "Bad request";
    break;

  default:
    echo "Internal server error";
    break;
  }
});

# hook for transforming value for {uid}
hook('uid', function ($uid, $db) {
  $uid = strtolower(trim($uid));
  return isset($db[$uid]) ? $db[$uid] : null;
});

# map route handler for any method
map('/users/{uid}', function ($args, $db) {

  # $args['uid'] has the db row (from the hook) or null
  if ($row = $args['uid'])
    return print "user: {$row[0]}, {$row[1]}, {$row[2]}\n";

  # trigger our 404 handler
  return error(404);
});

# arguments to dispatch() gets forwarded too
dispatch($db = [
  'u01' => ['anna', 28, 'f'],
  'u02' => ['rein', 33, 'f'],
  'u03' => ['john', 27, 'm'],
  'u04' => ['tina', 31, 'f'],
  'u05' => ['alex', 36, 'm']
]);
