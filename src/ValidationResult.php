<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Holds the result of a validation operation.
 *
 * @example
 * ```php
 * $result = Validator::field('email', $value)->email()->validate();
 *
 * if ($result->passes()) {
 *   $email = $result->value();
 * } else {
 *   echo $result->first();
 * }
 * ```
 */
class ValidationResult {

  private bool $_valid;
  private mixed $_value;
  private array $_errors;
  private string $_field;

  public function __construct(
    bool $valid,
    mixed $value = null,
    array $errors = [],
    string $field = ''
  ) {
    $this->_valid = $valid;
    $this->_value = $value;
    $this->_errors = $errors;
    $this->_field = $field;
  }

  // =========================================================================
  // Status Checks
  // =========================================================================

  /**
   * Check if validation passed.
   */
  public function passes():bool {
    return $this->_valid;
  }

  /**
   * Check if validation failed.
   */
  public function fails():bool {
    return !$this->_valid;
  }

  /**
   * Alias for passes().
   */
  public function isValid():bool {
    return $this->_valid;
  }

  // =========================================================================
  // Value Access
  // =========================================================================

  /**
   * Get the sanitized/validated value.
   *
   * @return mixed The value, or null if validation failed.
   */
  public function value():mixed {
    return $this->_value;
  }

  /**
   * Get the field name.
   */
  public function field():string {
    return $this->_field;
  }

  // =========================================================================
  // Error Access
  // =========================================================================

  /**
   * Get all errors.
   *
   * @return array ['field' => ['message1', 'message2'], ...]
   */
  public function errors():array {
    return $this->_errors;
  }

  /**
   * Get errors for a specific field.
   */
  public function errorsFor(string $field):array {
    return $this->_errors[$field] ?? [];
  }

  /**
   * Check if a field has errors.
   */
  public function hasError(string $field = ''):bool {
    if (empty($field)) {
      return !empty($this->_errors);
    }
    return !empty($this->_errors[$field]);
  }

  /**
   * Get the first error message.
   *
   * @param string $field Optional field name.
   * @return string|null
   */
  public function first(string $field = ''):?string {
    if (!empty($field)) {
      return $this->_errors[$field][0] ?? null;
    }

    foreach ($this->_errors as $messages) {
      if (!empty($messages)) {
        return $messages[0];
      }
    }
    return null;
  }

  /**
   * Get all error messages as a flat array.
   */
  public function messages():array {
    $messages = [];
    foreach ($this->_errors as $field => $errs) {
      foreach ($errs as $msg) {
        $messages[] = $msg;
      }
    }
    return $messages;
  }

  /**
   * Count total errors.
   */
  public function errorCount():int {
    $count = 0;
    foreach ($this->_errors as $errs) {
      $count += \count($errs);
    }
    return $count;
  }

  // =========================================================================
  // Utility
  // =========================================================================

  /**
   * Convert to array.
   */
  public function toArray():array {
    return [
      'valid'  => $this->_valid,
      'value'  => $this->_value,
      'field'  => $this->_field,
      'errors' => $this->_errors,
    ];
  }

  /**
   * Throw exception if validation failed.
   *
   * @throws ValidationException
   */
  public function throwIfFailed():self {
    if ($this->fails()) {
      throw new ValidationException($this->first() ?? 'Validation failed', $this->_errors);
    }
    return $this;
  }
}
