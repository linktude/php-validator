<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Main entry point for fluent single-field and bulk validation.
 *
 * @phpstan-type CompiledRule array{name: non-empty-string, params: list<mixed>}
 */
class Validator {

  /** @var array<string, mixed> */
  private array $_data = [];

  /** @var array<array-key, mixed> */
  private array $_rules = [];

  /** @var array<array-key, mixed> */
  private array $_messages = [];

  /** @var array<string, list<string>> */
  private array $_errors = [];

  /** @var array<string, mixed> */
  private array $_validated = [];

  public static function field(string $field, mixed $value):FieldValidator {
    return new FieldValidator($field, $value);
  }

  /**
   * @param array<string, mixed> $data
   * @param array<array-key, mixed> $rules
   * @param array<array-key, mixed> $messages
   */
  public static function make(array $data, array $rules, array $messages = []):BulkValidationResult {
    $validator = new self();
    $validator->_data = $data;
    $validator->_rules = $rules;
    $validator->_messages = $messages;

    return $validator->_runBulkValidation();
  }

  /**
   * @param array<string, mixed> $data
   * @param array<array-key, mixed> $rules
   * @param array<array-key, mixed> $messages
   * @return array<string, mixed>
   * @throws ValidationException
   */
  public static function validate(array $data, array $rules, array $messages = []):array {
    $result = self::make($data, $rules, $messages);
    if ($result->fails()) {
      throw new ValidationException(
        $result->first() ?? 'Validation failed',
        $result->errors()
      );
    }

    return $result->validated();
  }

  private function _runBulkValidation():BulkValidationResult {
    $this->_errors = [];
    $this->_validated = [];

    $compiledFields = $this->_compileRules();
    foreach ($compiledFields as $field => $rules) {
      $fieldValidator = new FieldValidator($field, $this->_getValue($field));
      $isNullable = \in_array('nullable', \array_column($rules, 'name'), true);
      if ($isNullable) {
        $fieldValidator->nullable();
      }

      foreach ($rules as $rule) {
        if ($rule['name'] !== 'nullable') {
          $this->_applyRule($fieldValidator, $rule, $field);
        }
      }

      $result = $fieldValidator->validate();
      if ($result->fails()) {
        $customMessage = $this->_messages[$field] ?? null;
        if (\is_string($customMessage) && $customMessage !== '') {
          $this->_errors[$field] = [$customMessage];
        } else {
          $this->_errors[$field] = $result->errorsFor($field);
        }
      } else {
        $this->_validated[$field] = $result->value();
      }
    }

    return new BulkValidationResult(
      $this->_errors === [],
      $this->_validated,
      $this->_errors
    );
  }

  /**
   * Compile every configured field before processing submitted values.
   *
   * @return array<string, list<CompiledRule>>
   */
  private function _compileRules():array {
    $parser = new RuleParser();
    $compiled = [];

    foreach ($this->_rules as $field => $rules) {
      if (!\is_string($field) || $field === '') {
        throw ValidationConfigurationException::forRule('', '(field)', 'field names must be non-empty strings');
      }

      if (\array_key_exists($field, $this->_messages) && !\is_string($this->_messages[$field])) {
        throw ValidationConfigurationException::forRule($field, '(message)', 'custom field messages must be strings');
      }

      $compiled[$field] = $parser->parse($rules, $field);
    }

    return $compiled;
  }

  private function _getValue(string $field):mixed {
    if (\array_key_exists($field, $this->_data)) {
      return $this->_data[$field];
    }

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

  /** @param CompiledRule $rule */
  private function _applyRule(FieldValidator $validator, array $rule, string $field):void {
    $name = $rule['name'];
    $params = $rule['params'];

    match ($name) {
      'required' => $validator->required(),
      'name' => $validator->name(
        $this->_stringListParam($params, 0, []),
        $this->_intParam($params, 1, 2),
        $this->_intParam($params, 2, 35)
      ),
      'username' => $validator->username(
        $this->_intParam($params, 0, 3),
        $this->_intParam($params, 1, 32),
        $this->_stringListParam($params, 2, []),
        $this->_stringParam($params, 3, 'UPPER'),
        $this->_stringListParam($params, 4, [])
      ),
      'email' => $validator->email(),
      'password' => $validator->password(
        $this->_intParam($params, 0, 8),
        $this->_intParam($params, 1, 128),
        $this->_boolParam($params, 2, true),
        $this->_boolParam($params, 3, true),
        $this->_boolParam($params, 4, true),
        $this->_boolParam($params, 5, true)
      ),
      'phone' => $validator->phone(),
      'url' => $validator->url(),
      'ip' => $validator->ip($this->_stringParam($params, 0, 'any')),
      'text' => $validator->text($this->_intParam($params, 0, 0), $this->_intParam($params, 1, 0)),
      'html' => $validator->html(
        $this->_intParam($params, 0, 0),
        $this->_intParam($params, 1, 0),
        $this->_stringListParam($params, 2, [])
      ),
      'slug' => $validator->slug($this->_intParam($params, 0, 1), $this->_intParam($params, 1, 128)),
      'uuid' => $validator->uuid(),
      'json' => $validator->json(),
      'hex' => $validator->hex($this->_intParam($params, 0, 0)),
      'color' => $validator->color(),
      'alpha' => $validator->alpha($this->_intParam($params, 0, 0), $this->_intParam($params, 1, 0)),
      'alphanumeric' => $validator->alphanumeric($this->_intParam($params, 0, 0), $this->_intParam($params, 1, 0)),
      'pattern' => $validator->pattern($this->_stringParam($params, 0)),
      'int' => $validator->int($this->_nullableIntParam($params, 0), $this->_nullableIntParam($params, 1)),
      'float' => $validator->float($this->_nullableFloatParam($params, 0), $this->_nullableFloatParam($params, 1)),
      'boolean' => $validator->boolean(),
      'date' => $validator->date(
        $this->_stringParam($params, 0, 'Y-m-d'),
        $this->_nullableStringParam($params, 1),
        $this->_nullableStringParam($params, 2)
      ),
      'time' => $validator->time($this->_nullableStringParam($params, 0), $this->_nullableStringParam($params, 1)),
      'datetime' => $validator->datetime($this->_nullableStringParam($params, 0), $this->_nullableStringParam($params, 1)),
      'in' => $validator->in($this->_listParam($params, 0), $this->_boolParam($params, 1, true)),
      'notIn' => $validator->notIn($this->_listParam($params, 0), $this->_boolParam($params, 1, true)),
      'array' => $validator->array($this->_intParam($params, 0, 0), $this->_intParam($params, 1, 0)),
      'creditcard' => $validator->creditcard(),
      'countryCode' => $validator->countryCode(),
      'currencyCode' => $validator->currencyCode(),
      'min' => $validator->min($this->_intParam($params, 0)),
      'max' => $validator->max($this->_intParam($params, 0)),
      'length' => $validator->length($this->_intParam($params, 0)),
      'between' => $validator->between($this->_intParam($params, 0), $this->_intParam($params, 1)),
      default => throw new \LogicException("Compiled validation rule '{$name}' for field '{$field}' cannot be dispatched."),
    };
  }

  /** @param list<mixed> $params */
  private function _intParam(array $params, int $index, ?int $default = null):int {
    $value = $params[$index] ?? $default;
    if (!\is_int($value)) throw new \LogicException('A compiled integer parameter is invalid.');
    return $value;
  }

  /** @param list<mixed> $params */
  private function _nullableIntParam(array $params, int $index):?int {
    $value = $params[$index] ?? null;
    if (!\is_int($value) && $value !== null) throw new \LogicException('A compiled integer parameter is invalid.');
    return $value;
  }

  /** @param list<mixed> $params */
  private function _nullableFloatParam(array $params, int $index):?float {
    $value = $params[$index] ?? null;
    if (!\is_float($value) && $value !== null) throw new \LogicException('A compiled numeric parameter is invalid.');
    return $value;
  }

  /** @param list<mixed> $params */
  private function _stringParam(array $params, int $index, ?string $default = null):string {
    $value = $params[$index] ?? $default;
    if (!\is_string($value)) throw new \LogicException('A compiled string parameter is invalid.');
    return $value;
  }

  /** @param list<mixed> $params */
  private function _nullableStringParam(array $params, int $index):?string {
    $value = $params[$index] ?? null;
    if (!\is_string($value) && $value !== null) throw new \LogicException('A compiled string parameter is invalid.');
    return $value;
  }

  /** @param list<mixed> $params */
  private function _boolParam(array $params, int $index, bool $default):bool {
    $value = $params[$index] ?? $default;
    if (!\is_bool($value)) throw new \LogicException('A compiled boolean parameter is invalid.');
    return $value;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function _listParam(array $params, int $index):array {
    $value = $params[$index] ?? null;
    if (!\is_array($value) || !\array_is_list($value)) {
      throw new \LogicException('A compiled list parameter is invalid.');
    }
    return $value;
  }

  /**
   * @param list<mixed> $params
   * @param list<string> $default
   * @return list<string>
   */
  private function _stringListParam(array $params, int $index, array $default):array {
    $value = $params[$index] ?? $default;
    if (!\is_array($value) || !\array_is_list($value)) {
      throw new \LogicException('A compiled string-list parameter is invalid.');
    }
    // Build the narrowed list by construction. Proving the element type
    // through flow analysis alone depends on the analyser version.
    $strings = [];
    foreach ($value as $entry) {
      if (!\is_string($entry)) throw new \LogicException('A compiled string-list parameter is invalid.');
      $strings[] = $entry;
    }

    return $strings;
  }
}
