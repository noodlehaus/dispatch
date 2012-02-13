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

Suite::add('partial()', function () {
	config('views', './tests');
	$str = partial('partial', array('name' => 'Foo'));
	assert(trim($str) === '<h1>Foo</h1>');
});

Suite::add('render()', function () {
	config('views', './tests');
	ob_start();
	render('view', array('name' => 'Foo'));
	$str = ob_get_clean();
	assert(trim($str) === '<div><h1>Foo</h1></div>');
});

Suite::run();
?>
