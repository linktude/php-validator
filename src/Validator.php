<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Main Validator class for bulk and single-field validation.
 *
 * @example
 * ```php
 * // Single field (fluent)
 * $result = Validator::field('email', $value)
 *   ->required()
 *   ->email()
 *   ->validate();
 *
 * // Bulk validation (string syntax)
 * $result = Validator::make($_POST, [
 *   'name'  => 'required|name',
 *   'email' => 'required|email',
 *   'age'   => 'int:18,120',
 * ]);
 *
 * // Bulk validation (array syntax)
 * $result = Validator::make($_POST, [
 *   'name'  => ['required', 'name'],
 *   'email' => ['required', 'email'],
 *   'age'   => ['int', 18, 120],
 * ]);
 *
 * if ($result->fails()) {
 *   $errors = $result->errors();
 * }
 *
 * $validated = $result->validated(); // Only validated fields
 * ```
 */
class Validator {

  private array $_data = [];
  private array $_rules = [];
  private array $_messages = [];
  private array $_errors = [];
  private array $_validated = [];

  // =========================================================================
  // Static Factory Methods
  // =========================================================================

  /**
   * Create a single-field validator.
   */
  public static function field(string $field, mixed $value):FieldValidator {
    return new FieldValidator($field, $value);
  }

  /**
   * Create a bulk validator and run validation.
   *
   * @param array $data Data to validate.
   * @param array $rules Validation rules.
   * @param array $messages Custom error messages.
   * @return BulkValidationResult
   */
  public static function make(
    array $data,
    array $rules,
    array $messages = []
  ):BulkValidationResult {
    $validator = new self();
    $validator->_data = $data;
    $validator->_rules = $rules;
    $validator->_messages = $messages;

    return $validator->_runBulkValidation();
  }

  /**
   * Validate and throw exception on failure.
   *
   * @throws ValidationException
   */
  public static function validate(
    array $data,
    array $rules,
    array $messages = []
  ):array {
    $result = self::make($data, $rules, $messages);

    if ($result->fails()) {
      throw new ValidationException(
        $result->first() ?? 'Validation failed',
        $result->errors()
      );
    }

    return $result->validated();
  }

  // =========================================================================
  // Bulk Validation
  // =========================================================================

  /**
   * Run bulk validation.
   */
  private function _runBulkValidation():BulkValidationResult {
    $this->_errors = [];
    $this->_validated = [];

    foreach ($this->_rules as $field => $rules) {
      $value = $this->_getValue($field);
      $fieldValidator = new FieldValidator($field, $value);

      // Parse rules
      $parsedRules = $this->_parseRules($rules);

      // Check if the field is required and whether a value was actually submitted
      $isRequired = \in_array('required', \array_column($parsedRules, 'name'), true);
      $isNullable = \in_array('nullable', \array_column($parsedRules, 'name'), true);
      $isAbsent   = ($value === null || $value === '' || $value === []);

      // If the field is not required and was not submitted, skip all validation.
      // Record the raw value (null / empty) without error and move on.
      if (!$isRequired && $isAbsent) {
        $this->_validated[$field] = $value;
        continue;
      }

      $fieldValidator = new FieldValidator($field, $value);

      if ($isNullable) {
        $fieldValidator->nullable();
      }

      // Apply rules
      foreach ($parsedRules as $rule) {
        if ($rule['name'] === 'nullable') {
          continue;
        }
        $this->_applyRule($fieldValidator, $rule);
      }

      // Validate
      $result = $fieldValidator->validate();

      if ($result->fails()) {
        // Use custom message if provided
        $customMessage = $this->_messages[$field] ?? null;
        if ($customMessage) {
          $this->_errors[$field] = [$customMessage];
        } else {
          $this->_errors[$field] = $result->errorsFor($field);
        }
      } else {
        $this->_validated[$field] = $result->value();
      }
    }

    return new BulkValidationResult(
      empty($this->_errors),
      $this->_validated,
      $this->_errors
    );
  }

  /**
   * Get value from data, supporting dot notation.
   */
  private function _getValue(string $field):mixed {
    if (\array_key_exists($field, $this->_data)) {
      return $this->_data[$field];
    }

    // Support dot notation (e.g., 'user.email')
    $keys = \explode('.', $field);
    $value = $this->_data;

    foreach ($keys as $key) {
      if (!\is_array($value) || !\array_key_exists($key, $value)) {
        return null;
      }
      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Parse rules from string or array format.
   *
   * @return array [['name' => 'rule', 'params' => [...]], ...]
   */
  private function _parseRules(mixed $rules):array {
    $parsed = [];

    if (\is_string($rules)) {
      // String format: 'required|email|max:255'
      $ruleList = \explode('|', $rules);

      foreach ($ruleList as $rule) {
        $rule = \trim($rule);
        if (empty($rule)) continue;

        if (\str_contains($rule, ':')) {
          [$name, $paramStr] = \explode(':', $rule, 2);
          $name = \trim($name);
          // Rules whose param looks like a regex literal (starts with a delimiter
          // such as /, ~, #, !, @) must NOT be comma-split — commas can appear
          // legitimately inside the pattern (e.g. {2,28}).
          $firstChar = \substr(\trim($paramStr), 0, 1);
          $isRegexParam = \in_array($firstChar, ['/', '~', '#', '!', '@'], true);
          if ($isRegexParam) {
            $params = [\trim($paramStr)];
          } else {
            $params = \explode(',', $paramStr);
            // Convert numeric strings to numbers
            $params = \array_map(function ($p) {
              $p = \trim($p);
              if (\is_numeric($p)) {
                return \str_contains($p, '.') ? (float)$p : (int)$p;
              }
              return $p;
            }, $params);
          }
        } else {
          $name = $rule;
          $params = [];
        }

        $parsed[] = ['name' => $name, 'params' => $params];
      }
    } elseif (\is_array($rules)) {
      foreach ($rules as $key => $rule) {
        if (\is_int($key)) {
          // Indexed array: ['required', 'email']
          if (\is_string($rule)) {
            $parsed[] = ['name' => $rule, 'params' => []];
          } elseif (\is_array($rule)) {
            // ['max', 255]
            $name = \array_shift($rule);
            $parsed[] = ['name' => $name, 'params' => $rule];
          }
        } else {
          // Associative: ['max' => 255]
          $parsed[] = ['name' => $key, 'params' => \is_array($rule) ? $rule : [$rule]];
        }
      }
    }

    return $parsed;
  }

  /**
   * Apply a parsed rule to the field validator.
   */
  private function _applyRule(FieldValidator $validator, array $rule):void {
    $name = $rule['name'];
    $params = $rule['params'];

    // Map rule names to methods
    $method = match ($name) {
      'required' => 'required',
      'name' => 'name',
      'username' => 'username',
      'email' => 'email',
      'password' => 'password',
      'phone', 'tel' => 'phone',
      'url' => 'url',
      'ip' => 'ip',
      'text' => 'text',
      'html' => 'html',
      'slug' => 'slug',
      'uuid' => 'uuid',
      'json' => 'json',
      'hex' => 'hex',
      'color' => 'color',
      'alpha' => 'alpha',
      'alphanumeric', 'alnum' => 'alphanumeric',
      'pattern', 'regex' => 'pattern',
      'int', 'integer' => 'int',
      'float', 'decimal', 'number' => 'float',
      'bool', 'boolean' => 'boolean',
      'date' => 'date',
      'time' => 'time',
      'datetime' => 'datetime',
      'in', 'option' => 'in',
      'not_in', 'notIn' => 'notIn',
      'array' => 'array',
      'creditcard' => 'creditcard',
      'country_code', 'countryCode' => 'countryCode',
      'currency_code', 'currencyCode' => 'currencyCode',
      'min' => 'min',
      'max' => 'max',
      'length' => 'length',
      'between' => 'between',
      default => null,
    };

    if ($method && \method_exists($validator, $method)) {
      // 'in' / 'notIn' receive their comma-split values as individual $params entries,
      // but the method signature is in(array $options) — wrap them into one array.
      if (\in_array($name, ['in', 'not_in', 'notIn', 'option'], true)) {
        $validator->$method($params);
      } else {
        $validator->$method(...$params);
      }
    }
  }
}

/**
 * Result for bulk validation.
 */
class BulkValidationResult {

  private bool $_valid;
  private array $_validated;
  private array $_errors;

  public function __construct(bool $valid, array $validated, array $errors) {
    $this->_valid = $valid;
    $this->_validated = $validated;
    $this->_errors = $errors;
  }

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

  /**
   * Get validated data.
   */
  public function validated():array {
    return $this->_validated;
  }

  /**
   * Get a specific validated value.
   */
  public function get(string $field, mixed $default = null):mixed {
    return $this->_validated[$field] ?? $default;
  }

  /**
   * Get all errors.
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
   */
  public function first(string $field = ''):?string {
    if (!empty($field)) {
      return $this->_errors[$field][0] ?? null;
    }

    foreach ($this->_errors as $f => $messages) {
      if (!empty($messages)) {
        return "'{$f}': {$messages[0]}";
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
        $messages[] = "'{$field}': {$msg}";
      }
    }
    return $messages;
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

  /**
   * Convert to array.
   */
  public function toArray():array {
    return [
      'valid'     => $this->_valid,
      'validated' => $this->_validated,
      'errors'    => $this->_errors,
    ];
  }
}
