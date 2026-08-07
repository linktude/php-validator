<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Exception thrown when validation fails.
 */
/**
 * @phpstan-type ErrorMap array<string, list<string>>
 */
class ValidationException extends \Exception {

  /** @var ErrorMap */
  private array $_errors;

  /** @param ErrorMap $errors */
  public function __construct(string $message, array $errors = [], int $code = 422) {
    parent::__construct($message, $code);
    $this->_errors = $errors;
  }

  /**
   * Get all validation errors.
   */
  /** @return ErrorMap */
  public function errors():array {
    return $this->_errors;
  }

  /**
   * Get errors for a specific field.
   */
  /** @return list<string> */
  public function errorsFor(string $field):array {
    return $this->_errors[$field] ?? [];
  }

  /**
   * Get the first error message.
   */
  public function first():?string {
    foreach ($this->_errors as $messages) {
      if (!empty($messages)) {
        return $messages[0];
      }
    }
    return $this->getMessage();
  }
}
