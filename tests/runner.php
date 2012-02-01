<?php
function test_context($name = null) {
	static $_context = array();
	if ($name != null) {
		array_push($_context, $name);
		return $name;
	}
	return array_pop($_context);
}

function test_case($name, $cb) {
	test_context($name);
	call_user_func($cb);
	echo "\033[32mSUCCESS:\033[0m ".test_context()."\n";
}

assert_options(ASSERT_ACTIVE,			1);
assert_options(ASSERT_QUIET_EVAL, 1);
assert_options(ASSERT_WARNING,		0);
assert_options(ASSERT_BAIL,				0);

assert_options(ASSERT_CALLBACK, function ($file, $line, $code) {
	echo "\033[31mFAILURE:\033[0m ".test_context()."\n  File: {$file}\n  Line: {$line}\n";
});
?>
