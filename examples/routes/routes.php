<?php
include '../../src/dispatch.php';

on('GET', '/index', function () {
  echo "GET /index invoked";
});

on('POST', '/index', function () {
  echo "POST /index invoked";
});

on('PUT', '/index', function () {
  echo "PUT /index invoked";
});

on('DELETE', '/index', function () {
  echo "DELETE /index invoked";
});

on('HEAD', '/index', function () {
  echo "HEAD /index invoked";
});

// match any request method
on('*', '/anything', function () {
  echo $_SERVER['REQUEST_METHOD'];
});

dispatch();
?>
