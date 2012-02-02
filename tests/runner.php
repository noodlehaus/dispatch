<?php
assert_options(ASSERT_ACTIVE,			1);
assert_options(ASSERT_QUIET_EVAL, 1);
assert_options(ASSERT_WARNING,		0);
assert_options(ASSERT_BAIL,				0);

class Suite {

	private static $_tests = array();
	private static $_queue = array();
	private static $_title = null;
	private static $_suite = null;
	private static $_jump  = false;

	public static function name($name) {
		self::$_suite = $name;
	}

	public static function add($name, $cb) {
		array_push(self::$_queue, $name);
		self::$_tests[$name] = $cb;
	}

	public static function context($title = null) {
		if ($title == null) {
			return self::$_title;
		}
		self::$_title = $title;
	}

	public static function jump() {
		self::$_jump = true;
	}

	public static function run() {

		echo "\nRunning tests for [".self::$_suite."]. Please wait.\n\n";

		$t1 = microtime(true);

		$passed = 0;
		$failed = 0;

		foreach (self::$_queue as $name) {
			self::context($name);
			call_user_func(self::$_tests[$name]);
			if (self::$_jump) {
				self::$_jump = false;
				continue;
			}
			echo "\033[32mSUCCESS:\033[0m ".self::context()."\n";
			++$passed;
		}

		$t1 = sprintf('%.4f', microtime(true) - $t1);

		$failed = count(self::$_queue) - $passed;

		if ($failed > 0) {
			echo "\nTime (sec): {$t1}, Passed: {$passed}, \033[31mFailed: {$failed}\033[0m\n\n";
		} else {
			echo "\nTime (sec): {$t1}, Passed: {$passed}, Failed: {$failed}\n\n";
		}
	}

}

assert_options(ASSERT_CALLBACK, function ($file, $line, $code) {
	echo "\033[31mFAILURE: ".Suite::context()."\n  File: {$file}\n  Line: {$line}\033[0m\n";
	Suite::jump();
});
?>
