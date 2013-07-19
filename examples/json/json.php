<?php
include '../../src/dispatch.php';

before(function () {
  scope('object', ['name' => 'Dispatch', 'type' => 'Framework']);
});

on('GET', '/json', function () {
  json_out(scope('object'));
});

on('GET', '/jsonp', function () {
  json_out(scope('object'), 'callback');
});

dispatch();
?>
