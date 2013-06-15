<?php
include '../src/dispatch.php';
include './tests-setup.php';

// test caching
cache_enable('memcached');

// memcached testing
config('cache.connection', 'localhost:11211');

// fetcher function
function get_users() {
  stash('missed', true);
  return array('jaydee', 'sheryl', 'addie');
}

// try out caching
$data = cache('users', 'get_users');
assert(is_array($data) && count($data) === 3);
assert(stash('missed') === true);

stash('missed', false);
$data = cache('users', 'get_users');
assert(is_array($data) && count($data) === 3);
assert(stash('missed') === false);

echo "cache-tests done!\n";
?>
