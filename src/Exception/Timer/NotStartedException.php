<?php

namespace Scoutapm\Exception\Timer;

class NotStartedException extends \Exception {

  public function __construct(string $message = '', int $code = 0, Throwable $previous = NULL) {
    parent::__construct('Can\'t stop a timer which isn\'t started.', $code, $previous);
  }

}
