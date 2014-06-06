<?php
include '../../src/dispatch.php';

before(function () {
  scope('object', ['name' => 'Dispatch', 'type' => 'Framework']);
});

on('GET', '/json', function () {
  json(scope('object'));
});

on('GET', '/jsonp', function () {
  json(scope('object'), 'callback');
});

dispatch();
?>
