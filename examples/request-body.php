<?php
include '../src/dispatch.php';

$form1 = <<<EOD
<form method="POST" action="/text">
  <input type="text" name="text">
  <input type="submit">
</form>
EOD;

$form2 = <<<EOD
<form method="POST" action="/file" enctype="multipart/form-data">
  <input type="file" name="file">
  <input type="submit">
</form>
EOD;

get('/text', function () use ($form1) {
  echo $form1;
});

post('/text', function () {
  $data = request_body();
  var_dump($data);
});

get('/file', function () use ($form2) {
  echo $form2;
});

post('/file', function () {
  $data = request_body();
  var_dump($data);
});

dispatch();
?>
