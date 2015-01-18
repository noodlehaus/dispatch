<?php
require __DIR__.'/../dispatch.php';

error_reporting(E_ALL|E_STRICT);

map('GET', '/index', function ($db) {
  $posts = '';
  foreach (file($db) as $post) {
    $posts .= phtml('post', ['post' => unserialize(trim($post))], false);
  }
  print phtml('index', ['posts' => $posts]);
});

# show a post
map('GET', '/posts/<id>', function ($args, $db) {
  foreach (file($db) as $post) {
    $post = unserialize($post);
    if ($post['id'] != $args['id']) {
      continue;
    }
    print phtml('post', ['post' => $post]);
  }
});

# new post form
map('GET', '/submit', function () {
  print phtml('submit', blanks('title', 'body'));
});

# create a new post
map('POST', '/create', function ($db) {

  $post = $_POST['post'];
  $post['id'] = time();

  file_put_contents($db, serialize($post)."\n", FILE_APPEND);

  return redirect('/index');
});

# load contents of config.ini
config(parse_ini_file(__DIR__.'/config.ini'));

# prep the db
!file_exists($db = __DIR__.'/posts.txt') && touch($db);

# pass along our data store
dispatch($db);
