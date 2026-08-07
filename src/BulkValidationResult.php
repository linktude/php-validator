<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Result for a bulk validation operation.
 *
 * @phpstan-type ErrorMap array<string, list<string>>
 */
class BulkValidationResult {

  private bool $_valid;

  /** @var array<string, mixed> */
  private array $_validated;

  /** @var ErrorMap */
  private array $_errors;

  /**
   * @param array<string, mixed> $validated
   * @param ErrorMap $errors
   */
  public function __construct(bool $valid, array $validated, array $errors) {
    $this->_valid = $valid;
    $this->_validated = $validated;
    $this->_errors = $errors;
  }

  public function passes():bool {
    return $this->_valid;
  }

  public function fails():bool {
    return !$this->_valid;
  }

  public function isValid():bool {
    return $this->_valid;
  }

  /** @return array<string, mixed> */
  public function validated():array {
    return $this->_validated;
  }

  public function get(string $field, mixed $default = null):mixed {
    return $this->_validated[$field] ?? $default;
  }

  /** @return ErrorMap */
  public function errors():array {
    return $this->_errors;
  }

  /** @return list<string> */
  public function errorsFor(string $field):array {
    return $this->_errors[$field] ?? [];
  }

  public function hasError(string $field = ''):bool {
    if ($field === '') {
      return $this->_errors !== [];
    }

    return ($this->_errors[$field] ?? []) !== [];
  }

  public function first(string $field = ''):?string {
    if ($field !== '') {
      return $this->_errors[$field][0] ?? null;
    }

    foreach ($this->_errors as $errorField => $messages) {
      if ($messages !== []) {
        return "'{$errorField}': {$messages[0]}";
      }
    }

    return null;
  }

  /** @return list<string> */
  public function messages():array {
    $messages = [];
    foreach ($this->_errors as $field => $errors) {
      foreach ($errors as $message) {
        $messages[] = "'{$field}': {$message}";
      }
    }

    return $messages;
  }

  public function errorCount():int {
    $count = 0;
    foreach ($this->_errors as $errors) {
      $count += \count($errors);
    }

    return $count;
  }

  /** @throws ValidationException */
  public function throwIfFailed():self {
    if ($this->fails()) {
      throw new ValidationException($this->first() ?? 'Validation failed', $this->_errors);
    }

    return $this;
  }

  /**
   * @return array{valid: bool, validated: array<string, mixed>, errors: ErrorMap}
   */
  public function toArray():array {
    return [
      'valid' => $this->_valid,
      'validated' => $this->_validated,
      'errors' => $this->_errors,
    ];
  }
}
