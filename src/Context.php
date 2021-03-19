<?php

/**
 * @author noodlehaus
 * @license MIT
 */

declare(strict_types=1);

namespace Dispatch;

use BadMethodCallException;

class Context {

  private static ?Application $application = null;

  private function __construct() {}

  public function __clone() {
    throw new BadMethodCallException("Cloning not allowed.");
  }

  public function __wakeup() {
    throw new BadMethodCallException("Deserialization not allowed.");
  }

  public function __sleep() {
    throw new BadMethodCallException("Serialization not allowed.");
  }

  public static function getApplication(): Application {
    if (self::$application === null) {
      self::$application = new Application();
    }
    return self::$application;
  }
}
