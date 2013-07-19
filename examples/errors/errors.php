<?php
include '../../src/dispatch.php';

error(404, function () {
  echo "Sorry, page not found!";
});

dispatch();
?>
