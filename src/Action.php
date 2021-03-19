<?php

/**
 * @author noodlehaus
 * @license MIT
 */

declare(strict_types=1);

namespace Dispatch;

class Action {

  private string $expression;
  private array $captures;

  public function __construct(
    private string $method,
    private string $pattern,
    private $callback,
  ) {
    $this->expression = preg_replace('@:(\w+)@', '(?<\1>[^/]+)', $this->pattern);
    $this->captures = [];
  }

  public function getCaptures(): array {
    return $this->captures;
  }

  public function matches(string $requestMethod, string $requestUri): bool {
    return (
      strtoupper($requestMethod) === $this->method &&
      preg_match("@^{$this->expression}$@", $requestUri, $this->captures)
    );
  }

  public function process(string $requestPath, ...$args) {
    $captures = array_slice($this->captures, 1);
    return empty($captures)
      ? ($this->callback)(...$args)
      : ($this->callback)($captures, ...$args);
  }
}
