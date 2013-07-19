<?php
include '../../src/dispatch.php';

error(404, function () {
  echo "Sorry, page not found!<br>";
});

error(404, function () {
  echo "Sorry, we really can't find your page!<br>";
});

dispatch();
?>
