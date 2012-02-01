<?php
test_case('method()', function () {
	$_SERVER['REQUEST_METHOD'] = 'post';
	assert(method() == 'POST');
	assert(method('POST') == true);
});

test_case('html()', function () {
	assert('&amp;' == html('&'));
	assert('&quot;' == html('"'));
});

test_case('from()', function () {
	$data = array('name' => 'jaydee', 'age' => 32, 'location' => 'singapore');
	assert(from($data, 'name') == $data['name']);
	assert(from($data, array('name', 'age')) == array('name' => $data['name'], 'age' => $data['age']));
});

test_case('stash()', function () {
	$data = array('name' => 'jaydee');
	stash('data', $data);
	assert(stash('data') == $data);
});

test_case('config()', function () {
	config('views', 'views/');
	assert(config('views') == 'views/');
});

test_case('precondition()', function () {
	precondition('setup_stash', function () {
		stash('stash', true);
	});
	precondition('setup_stash');
	assert(stash('stash') == true);
});
?>
