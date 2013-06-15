<?php
// custom error handler
function oh_crap($errno, $errstr, $errfile, $errline) {
  echo "Test failed at line [{$errline}]\n";
}

// custom error handler
set_error_handler('oh_crap');

// custom assert
assert_options(ASSERT_ACTIVE, true);
assert_options(ASSERT_BAIL, false);
assert_options(ASSERT_QUIET_EVAL, true);
assert_options(ASSERT_WARNING, true);
assert_options(ASSERT_CALLBACK, function ($file, $line, $message) {
  oh_crap(E_USER_NOTICE, $message, $file, $line);
});
?>
