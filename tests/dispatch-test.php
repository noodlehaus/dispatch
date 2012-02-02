<?php
Suite::name('dispatch php micro framework tests');

Suite::add('method()', function () {
	$_SERVER['REQUEST_METHOD'] = 'post';
	assert(method() == 'POST');
	assert(method('POST') == true);
});

Suite::add('html()', function () {
	assert('&amp;' == html('&'));
	assert('&quot;' == html('"'));
});

Suite::add('from()', function () {
	$data = array('name' => 'jaydee', 'age' => 32, 'location' => 'singapore');
	assert(from($data, 'name') == $data['name']);
	assert(from($data, array('name', 'age')) == array('name' => $data['name'], 'age' => $data['age']));
});

Suite::add('stash()', function () {
	$data = array('name' => 'jaydee');
	stash('data', $data);
	assert(stash('data') == $data);
});

Suite::add('config()', function () {
	config('views', 'views/');
	assert(config('views') == 'views/');
});

Suite::add('precondition()', function () {
	precondition('setup_stash', function () {
		stash('stash', true);
	});
	precondition('setup_stash');
	assert(stash('stash') == true);
});

Suite::run();
?>
