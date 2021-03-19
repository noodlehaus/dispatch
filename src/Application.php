<?php

/**
 * @author noodlehaus
 * @license MIT
 */

declare(strict_types=1);

namespace Dispatch;

class Application {

  protected array $actions;

  public function __construct(Action ...$actions) {
    $this->actions = $actions;
  }

  public function addRoute(string $method, string $pattern, callable $callback) {
    array_push($this->actions, new Action($method, $pattern, $callback));
  }

  public function dispatch(string $method, string $uri, ...$args) {
    $method = strtoupper($method);
    $path = trim(rawurldecode(parse_url($uri, PHP_URL_PATH)), '/');
    $response = null;
    foreach ($this->actions as $action) {
      if ($action->matches($method, $uri)) {
        $response = $action->process($uri, ...$args);
        break;
      }
    }
    $this->render($response ?? new Response('', 404));
  }

  public function render(Response $response) {
    $filteredHeaders = $this->filterHeaders($response->getHeaders());
    http_response_code($response->getStatus());
    foreach ($filteredHeaders as [$name, $value]) {
      header($name.': '.$value);
    }
    print $response->getContent();
  }

  protected function filterHeaders(array $unfilteredHeaders) {
    $filteredHeaders = [];
    foreach ($unfilteredHeaders as $name => $entry) {
      if (! preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
        throw new InvalidArgumentException("Invalid header name - {$name}");
      }
      $values = is_array($entry) ? $entry : [$entry];
      foreach ($values as $value) {
        if (
          preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", $value) ||
          preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $value)
        ) {
          throw new InvalidArgumentException("Invalid header value - {$value}");
        }
      }
      $filteredHeaders[] = [$name, implode(',', $values)];
    }
    return $filteredHeaders;
  }
}
