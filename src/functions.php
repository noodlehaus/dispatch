<?php

/**
 * @author noodlehaus
 * @license MIT
 */

declare(strict_types=1);

namespace Dispatch;

function dispatch(...$args) {
  return Context::getApplication()->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI'],
    ...$args,
  );
}

function route(string $method, string $pattern, callable $callback) {
  Context::getApplication()->addRoute($method, $pattern, $callback);
}

function response(string $content, int $status = 200, array $headers = []) {
  return new Response($content, $status, $headers);
}

function redirect(string $location, int $status = 302) {
  return new Response('', $status, ['Location' => $location]);
}

function page(string $path, array $context = []) {
  return fn () => new Response(phtml($path, $context));
}

function phtml(string $path, ?array $context = []) {
  ob_start();
  extract($context, EXTR_SKIP);
  require preg_replace('@.phtml$@i', '', $path).'.phtml';
  return trim(ob_get_clean());
}
