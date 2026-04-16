<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Fluent single-field validator.
 *
 * @example
 * ```php
 * $result = Validator::field('email', $value)
 *   ->required()
 *   ->email()
 *   ->validate();
 *
 * if ($result->passes()) {
 *   $email = $result->value();
 * }
 * ```
 */
class FieldValidator {

  private string $_field;
  private mixed $_value;
  private mixed $_sanitized;
  private array $_rules = [];
  private array $_errors = [];
  private bool $_nullable = false;
  private bool $_bail = true; // Stop on first error

  public function __construct(string $field, mixed $value) {
    $this->_field = $field;
    $this->_value = $value;
    $this->_sanitized = $value;
  }

  // =========================================================================
  // Configuration
  // =========================================================================

  /**
   * Allow null/empty values (skip validation if empty).
   */
  public function nullable():self {
    $this->_nullable = true;
    return $this;
  }

  /**
   * Continue validation even after errors (default: stop on first).
   */
  public function bail(bool $bail = true):self {
    $this->_bail = $bail;
    return $this;
  }

  /**
   * Set custom error message for the last rule.
   */
  public function message(string $message):self {
    if (!empty($this->_rules)) {
      $lastIndex = \count($this->_rules) - 1;
      $this->_rules[$lastIndex]['message'] = $message;
    }
    return $this;
  }

  // =========================================================================
  // Core Rules
  // =========================================================================

  /**
   * Value must be present and not empty.
   */
  public function required():self {
    $this->_rules[] = [
      'rule' => function ($value) {
        if ($value === null || $value === '' || $value === []) {
          return [false, null, 'This field is required.'];
        }
        return [true, $value, null];
      },
      'message' => null,
    ];
    return $this;
  }

  // =========================================================================
  // String Rules
  // =========================================================================

  public function name(array $restricted = [], int $min = 2, int $max = 35):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::name($v, $restricted, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function username(
    int $min = 3,
    int $max = 32,
    array $restricted = [],
    string $case = 'UPPER',
    array $allowed_chars = []
  ):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::username($v, $min, $max, $restricted, $case, $allowed_chars),
      'message' => null,
    ];
    return $this;
  }

  public function email():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::email($v),
      'message' => null,
    ];
    return $this;
  }

  public function password(
    int $min = 8,
    int $max = 128,
    bool $upper = true,
    bool $lower = true,
    bool $number = true,
    bool $special = true
  ):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::password($v, $min, $max, $upper, $lower, $number, $special),
      'message' => null,
    ];
    return $this;
  }

  public function phone():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::phone($v),
      'message' => null,
    ];
    return $this;
  }

  public function tel():self {
    return $this->phone();
  }

  public function url():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::url($v),
      'message' => null,
    ];
    return $this;
  }

  public function ip(string $version = 'any'):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::ip($v, $version),
      'message' => null,
    ];
    return $this;
  }

  public function text(int $min = 0, int $max = 0):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::text($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function html(int $min = 0, int $max = 0, array $allowed_tags = []):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::html($v, $min, $max, $allowed_tags),
      'message' => null,
    ];
    return $this;
  }

  public function slug(int $min = 1, int $max = 128):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::slug($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function uuid():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::uuid($v),
      'message' => null,
    ];
    return $this;
  }

  public function json():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::json($v),
      'message' => null,
    ];
    return $this;
  }

  public function hex(int $length = 0):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::hex($v, $length),
      'message' => null,
    ];
    return $this;
  }

  public function color():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::color($v),
      'message' => null,
    ];
    return $this;
  }

  public function alpha(int $min = 0, int $max = 0):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::alpha($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function alphanumeric(int $min = 0, int $max = 0):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::alphanumeric($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function pattern(string $regex):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::pattern($v, $regex),
      'message' => null,
    ];
    return $this;
  }

  public function regex(string $regex):self {
    return $this->pattern($regex);
  }

  // =========================================================================
  // Numeric Rules
  // =========================================================================

  public function int(?int $min = null, ?int $max = null):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::int($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function integer(?int $min = null, ?int $max = null):self {
    return $this->int($min, $max);
  }

  public function float(?float $min = null, ?float $max = null):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::float($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function decimal(?float $min = null, ?float $max = null):self {
    return $this->float($min, $max);
  }

  public function boolean():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::boolean($v),
      'message' => null,
    ];
    return $this;
  }

  public function bool():self {
    return $this->boolean();
  }

  // =========================================================================
  // Date/Time Rules
  // =========================================================================

  public function date(string $format = 'Y-m-d', ?string $min = null, ?string $max = null):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::date($v, $format, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function time(?string $min = null, ?string $max = null):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::time($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function datetime(?string $min = null, ?string $max = null):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::datetime($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  // =========================================================================
  // Choice Rules
  // =========================================================================

  public function in(array $options, bool $strict = true):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::in($v, $options, $strict),
      'message' => null,
    ];
    return $this;
  }

  public function option(array $options, bool $strict = true):self {
    return $this->in($options, $strict);
  }

  public function notIn(array $options, bool $strict = true):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::notIn($v, $options, $strict),
      'message' => null,
    ];
    return $this;
  }

  // =========================================================================
  // Array Rules
  // =========================================================================

  public function array(int $min = 0, int $max = 0):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::array($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  public function arrayOf(string $rule, array $params = []):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::arrayOf($v, $rule, $params),
      'message' => null,
    ];
    return $this;
  }

  // =========================================================================
  // Special Rules
  // =========================================================================

  public function creditcard():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::creditcard($v),
      'message' => null,
    ];
    return $this;
  }

  public function fileExtension(array $allowed):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::fileExtension($v, $allowed),
      'message' => null,
    ];
    return $this;
  }

  public function countryCode():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::countryCode($v),
      'message' => null,
    ];
    return $this;
  }

  public function currencyCode():self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::currencyCode($v),
      'message' => null,
    ];
    return $this;
  }

  // =========================================================================
  // Length Rules
  // =========================================================================

  public function min(int $min):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::minLength($v, $min),
      'message' => null,
    ];
    return $this;
  }

  public function max(int $max):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::maxLength($v, $max),
      'message' => null,
    ];
    return $this;
  }

  public function length(int $length):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::length($v, $length),
      'message' => null,
    ];
    return $this;
  }

  public function between(int $min, int $max):self {
    $this->_rules[] = [
      'rule' => fn($v) => Rules::lengthBetween($v, $min, $max),
      'message' => null,
    ];
    return $this;
  }

  // =========================================================================
  // Custom Rule
  // =========================================================================

  /**
   * Add a custom validation rule.
   *
   * @param callable $callback Receives value, returns [bool $valid, mixed $value, ?string $error].
   */
  public function custom(callable $callback):self {
    $this->_rules[] = [
      'rule' => $callback,
      'message' => null,
    ];
    return $this;
  }

  // =========================================================================
  // Execution
  // =========================================================================

  /**
   * Execute validation and return result.
   */
  public function validate():ValidationResult {
    // Handle nullable
    if ($this->_nullable && ($this->_value === null || $this->_value === '' || $this->_value === [])) {
      return new ValidationResult(true, $this->_value, [], $this->_field);
    }

    $this->_errors = [];
    $this->_sanitized = $this->_value;

    foreach ($this->_rules as $ruleData) {
      $rule = $ruleData['rule'];
      $customMessage = $ruleData['message'];

      $result = $rule($this->_sanitized);

      if (!$result[0]) {
        $this->_errors[$this->_field][] = $customMessage ?? $result[2];

        if ($this->_bail) {
          break;
        }
      } else {
        // Update sanitized value
        $this->_sanitized = $result[1];
      }
    }

    $valid = empty($this->_errors);
    return new ValidationResult(
      $valid,
      $valid ? $this->_sanitized : null,
      $this->_errors,
      $this->_field
    );
  }

  /**
   * Execute validation and return value or false.
   */
  public function value():mixed {
    $result = $this->validate();
    return $result->passes() ? $result->value() : false;
  }

  /**
   * Execute validation and throw if failed.
   *
   * @throws ValidationException
   */
  public function validateOrFail():mixed {
    $result = $this->validate();
    $result->throwIfFailed();
    return $result->value();
  }
}
