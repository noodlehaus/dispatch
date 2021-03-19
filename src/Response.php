<?php

/**
 * @author noodlehaus
 * @license MIT
 */

declare(strict_types=1);

namespace Dispatch;

class Response {

  public function __construct(
    private string $content,
    private int $status = 200,
    private array $headers = [],
  ) {}

  public function getHeaders(): array {
    return $this->headers;
  }

  public function getContent(): string {
    return $this->content;
  }

  public function getStatus(): int {
    return $this->status;
  }
}
