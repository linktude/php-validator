<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Parses and compiles bulk validation rule configuration.
 *
 * Parsing is deliberately independent of submitted values.
 *
 * @internal
 * @phpstan-type CompiledRule array{name: non-empty-string, params: list<mixed>}
 */
class RuleParser {

  /** @var array<string, non-empty-string> */
  private const ALIASES = [
    'required' => 'required',
    'nullable' => 'nullable',
    'name' => 'name',
    'username' => 'username',
    'email' => 'email',
    'password' => 'password',
    'phone' => 'phone',
    'tel' => 'phone',
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
    'alphanumeric' => 'alphanumeric',
    'alnum' => 'alphanumeric',
    'pattern' => 'pattern',
    'regex' => 'pattern',
    'int' => 'int',
    'integer' => 'int',
    'float' => 'float',
    'decimal' => 'float',
    'number' => 'float',
    'boolean' => 'boolean',
    'bool' => 'boolean',
    'date' => 'date',
    'time' => 'time',
    'datetime' => 'datetime',
    'in' => 'in',
    'option' => 'in',
    'notin' => 'notIn',
    'not_in' => 'notIn',
    'array' => 'array',
    'creditcard' => 'creditcard',
    'countrycode' => 'countryCode',
    'country_code' => 'countryCode',
    'currencycode' => 'currencyCode',
    'currency_code' => 'currencyCode',
    'min' => 'min',
    'max' => 'max',
    'length' => 'length',
    'between' => 'between',
  ];

  private const PCRE_MODIFIERS = 'imsxADSUXJun';

  /**
   * @return list<CompiledRule>
   */
  public function parse(mixed $rules, string $field = ''):array {
    if (\is_string($rules)) {
      return $this->parseStringRules($rules, $field);
    }

    if (\is_array($rules)) {
      return $this->parseArrayRules($rules, $field);
    }

    throw ValidationConfigurationException::forRule($field, '(rules)', 'rules must be a string or array');
  }

  /**
   * @return list<CompiledRule>
   */
  private function parseStringRules(string $rules, string $field):array {
    $length = \strlen($rules);
    $offset = 0;
    $compiled = [];

    if (\trim($rules) === '') {
      throw ValidationConfigurationException::forRule($field, '(empty)', 'at least one rule is required');
    }

    while ($offset < $length) {
      $this->skipWhitespace($rules, $offset);
      if ($offset >= $length || $rules[$offset] === '|') {
        throw ValidationConfigurationException::forRule($field, '(empty)', 'empty rule names are not allowed');
      }

      $nameStart = $offset;
      while ($offset < $length && $rules[$offset] !== ':' && $rules[$offset] !== '|') {
        $offset++;
      }

      $name = \trim(\substr($rules, $nameStart, $offset - $nameStart));
      if ($name === '') {
        throw ValidationConfigurationException::forRule($field, '(empty)', 'empty rule names are not allowed');
      }

      $params = [];
      if ($offset < $length && $rules[$offset] === ':') {
        $offset++;
        if ($this->isPatternAlias($name)) {
          [$pattern, $offset] = $this->readRegex($rules, $offset, $field, $name);
          $params = [$pattern];
        } else {
          $paramsStart = $offset;
          while ($offset < $length && $rules[$offset] !== '|') {
            $offset++;
          }

          $rawParams = \trim(\substr($rules, $paramsStart, $offset - $paramsStart));
          if ($rawParams === '') {
            throw ValidationConfigurationException::forRule($field, $name, 'required parameters are missing');
          }

          $params = \array_map('trim', \explode(',', $rawParams));
          foreach ($params as $param) {
            if ($param === '') {
              throw ValidationConfigurationException::forRule($field, $name, 'empty parameters are not allowed');
            }
          }
        }
      }

      $compiled[] = $this->compile($name, $params, 'string', $field);
      $this->skipWhitespace($rules, $offset);

      if ($offset >= $length) {
        break;
      }

      if ($rules[$offset] !== '|') {
        throw ValidationConfigurationException::forRule($field, $name, 'unexpected characters follow the rule');
      }

      $offset++;
      if ($offset >= $length || \trim(\substr($rules, $offset)) === '') {
        throw ValidationConfigurationException::forRule($field, '(empty)', 'empty rule names are not allowed');
      }
    }

    return $compiled;
  }

  /**
   * @param array<mixed> $rules
   * @return list<CompiledRule>
   */
  private function parseArrayRules(array $rules, string $field):array {
    if ($rules === []) {
      throw ValidationConfigurationException::forRule($field, '(empty)', 'at least one rule is required');
    }

    $compiled = [];
    foreach ($rules as $key => $rule) {
      if (\is_int($key)) {
        if (\is_string($rule)) {
          foreach ($this->parseStringRules($rule, $field) as $parsedRule) {
            $compiled[] = $parsedRule;
          }
          continue;
        }

        if (!\is_array($rule) || !\array_is_list($rule) || $rule === []) {
          throw ValidationConfigurationException::forRule(
            $field,
            '(array)',
            'indexed entries must be bare rule strings or nested parameterized tuples'
          );
        }

        $tuple = $rule;
        $name = \array_shift($tuple);
        if (!\is_string($name) || \trim($name) === '') {
          throw ValidationConfigurationException::forRule($field, '(empty)', 'tuple rule names must be non-empty strings');
        }

        $compiled[] = $this->compile($name, $tuple, 'tuple', $field);
        continue;
      }

      if (\trim($key) === '') {
        throw ValidationConfigurationException::forRule($field, '(empty)', 'associative rule names must be non-empty strings');
      }

      if (\is_array($rule)) {
        if (!\array_is_list($rule)) {
          throw ValidationConfigurationException::forRule($field, $key, 'associative parameters must be a list');
        }
        $params = $rule;
      } else {
        $params = [$rule];
      }

      $compiled[] = $this->compile($key, $params, 'associative', $field);
    }

    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return CompiledRule
   */
  private function compile(string $rawName, array $params, string $form, string $field):array {
    $lookup = \strtolower(\trim($rawName));
    $name = self::ALIASES[$lookup] ?? null;
    if ($name === null) {
      throw ValidationConfigurationException::forRule($field, \trim($rawName), 'unknown rules are not supported');
    }

    $compiledParams = match ($name) {
      'required', 'nullable', 'email', 'phone', 'url', 'uuid', 'json', 'color',
      'boolean', 'creditcard', 'countryCode', 'currencyCode' => $this->compileNoParams($params, $field, $rawName),
      'name' => $this->compileName($params, $form, $field, $rawName),
      'username' => $this->compileUsername($params, $form, $field, $rawName),
      'password' => $this->compilePassword($params, $form, $field, $rawName),
      'ip' => $this->compileIp($params, $form, $field, $rawName),
      'text', 'alpha', 'alphanumeric' => $this->compileIntegerRange($params, $form, $field, $rawName, true),
      'slug' => $this->compileIntegerRange($params, $form, $field, $rawName, false),
      'html' => $this->compileHtml($params, $form, $field, $rawName),
      'hex' => $this->compileHex($params, $form, $field, $rawName),
      'pattern' => $this->compilePattern($params, $field, $rawName),
      'int' => $this->compileNullableIntegerRange($params, $form, $field, $rawName),
      'float' => $this->compileNullableFloatRange($params, $form, $field, $rawName),
      'date' => $this->compileDate($params, $field, $rawName),
      'time' => $this->compileTime($params, $field, $rawName),
      'datetime' => $this->compileDatetime($params, $field, $rawName),
      'in', 'notIn' => $this->compileChoice($params, $form, $field, $rawName),
      'array' => $this->compileIntegerRange($params, $form, $field, $rawName, true),
      'min', 'max', 'length' => $this->compileExactIntegers($params, 1, $form, $field, $rawName),
      'between' => $this->compileExactIntegers($params, 2, $form, $field, $rawName),
    };

    return ['name' => $name, 'params' => $compiledParams];
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileNoParams(array $params, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, 0, $field, $rule);
    return [];
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileName(array $params, string $form, string $field, string $rule):array {
    if ($params === []) {
      return [];
    }

    if ($form === 'string') {
      $this->assertParameterCount($params, 2, 2, $field, $rule);
      $min = $this->toInt($params[0], true, $field, $rule);
      $max = $this->toInt($params[1], true, $field, $rule);
      $this->assertNonNegativeRange($min, $max, $field, $rule);
      return [[], $min, $max];
    }

    $this->assertParameterCount($params, 1, 3, $field, $rule);
    $restricted = $this->toStringList($params[0], $field, $rule);
    $compiled = [$restricted];
    $min = null;
    $max = null;
    if (isset($params[1])) {
      $min = $this->toInt($params[1], false, $field, $rule);
      $compiled[] = $min;
    }
    if (isset($params[2])) {
      $max = $this->toInt($params[2], false, $field, $rule);
      $compiled[] = $max;
    }
    if ($min !== null && $max !== null) {
      $this->assertNonNegativeRange($min, $max, $field, $rule);
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileUsername(array $params, string $form, string $field, string $rule):array {
    if ($form === 'string' && $params !== []) {
      $this->assertParameterCount($params, 2, 2, $field, $rule);
    } else {
      $this->assertParameterCount($params, 0, 5, $field, $rule);
    }

    $compiled = [];
    $min = isset($params[0]) ? $this->toInt($params[0], $form === 'string', $field, $rule) : null;
    $max = isset($params[1]) ? $this->toInt($params[1], $form === 'string', $field, $rule) : null;
    if ($min !== null) $compiled[] = $min;
    if ($max !== null) $compiled[] = $max;
    if (isset($params[2])) $compiled[] = $this->toStringList($params[2], $field, $rule);
    if (isset($params[3])) {
      $case = \strtoupper($this->toString($params[3], $field, $rule));
      if (!\in_array($case, ['UPPER', 'UPPERCASE', 'LOWER', 'LOWERCASE', 'PRESERVE'], true)) {
        throw ValidationConfigurationException::forRule($field, $rule, 'the username case policy is invalid');
      }
      $compiled[] = $case;
    }
    if (isset($params[4])) {
      $characters = $this->toStringList($params[4], $field, $rule);
      foreach ($characters as $character) {
        if (\mb_strlen($character) !== 1) {
          throw ValidationConfigurationException::forRule($field, $rule, 'allowed username characters must contain one character each');
        }
      }
      $compiled[] = $characters;
    }

    if ($min !== null && $max !== null) {
      $this->assertNonNegativeRange($min, $max, $field, $rule);
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compilePassword(array $params, string $form, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, 6, $field, $rule);
    $compiled = [];
    $min = isset($params[0]) ? $this->toInt($params[0], $form === 'string', $field, $rule) : null;
    $max = isset($params[1]) ? $this->toInt($params[1], $form === 'string', $field, $rule) : null;
    if ($min !== null) $compiled[] = $min;
    if ($max !== null) $compiled[] = $max;
    for ($index = 2; $index < \count($params); $index++) {
      $compiled[] = $this->toBool($params[$index], $form === 'string', $field, $rule);
    }
    if ($min !== null && $max !== null) {
      $this->assertNonNegativeRange($min, $max, $field, $rule);
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileIp(array $params, string $form, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, 1, $field, $rule);
    if ($params === []) return [];
    $version = \strtolower($this->toString($params[0], $field, $rule));
    if (!\in_array($version, ['any', 'v4', 'ipv4', 'v6', 'ipv6'], true)) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the IP version must be any, v4, ipv4, v6, or ipv6');
    }
    return [$version];
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileIntegerRange(
    array $params,
    string $form,
    string $field,
    string $rule,
    bool $zeroMaximumIsUnbounded
  ):array {
    $this->assertParameterCount($params, 0, 2, $field, $rule);
    $compiled = [];
    foreach ($params as $param) {
      $compiled[] = $this->toInt($param, $form === 'string', $field, $rule);
    }
    if (isset($compiled[0], $compiled[1])) {
      $this->assertNonNegativeRange($compiled[0], $compiled[1], $field, $rule, $zeroMaximumIsUnbounded);
    } elseif (isset($compiled[0]) && $compiled[0] < 0) {
      throw ValidationConfigurationException::forRule($field, $rule, 'length and count parameters cannot be negative');
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileHtml(array $params, string $form, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, $form === 'string' ? 2 : 3, $field, $rule);
    $compiled = [];
    $min = isset($params[0]) ? $this->toInt($params[0], $form === 'string', $field, $rule) : null;
    $max = isset($params[1]) ? $this->toInt($params[1], $form === 'string', $field, $rule) : null;
    if ($min !== null) $compiled[] = $min;
    if ($max !== null) $compiled[] = $max;
    if (isset($params[2])) $compiled[] = $this->toStringList($params[2], $field, $rule);
    if ($min !== null && $max !== null) {
      $this->assertNonNegativeRange($min, $max, $field, $rule, true);
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileHex(array $params, string $form, string $field, string $rule):array {
    $compiled = $this->compileExactIntegers($params, $params === [] ? 0 : 1, $form, $field, $rule);
    if (isset($compiled[0]) && $compiled[0] < 0) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the length cannot be negative');
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compilePattern(array $params, string $field, string $rule):array {
    $this->assertParameterCount($params, 1, 1, $field, $rule);
    $regex = $this->toString($params[0], $field, $rule);
    [$pattern, $offset] = $this->readRegex($regex, 0, $field, $rule);
    if ($offset !== \strlen($regex)) {
      throw ValidationConfigurationException::forRule($field, $rule, 'unexpected characters follow the regular expression');
    }
    return [$pattern];
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileNullableIntegerRange(array $params, string $form, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, 2, $field, $rule);
    $compiled = [];
    foreach ($params as $param) {
      $compiled[] = $param === null ? null : $this->toInt($param, $form === 'string', $field, $rule);
    }
    if (isset($compiled[0], $compiled[1]) && $compiled[0] > $compiled[1]) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the minimum cannot exceed the maximum');
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileNullableFloatRange(array $params, string $form, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, 2, $field, $rule);
    $compiled = [];
    foreach ($params as $param) {
      $compiled[] = $param === null ? null : $this->toFloat($param, $form === 'string', $field, $rule);
    }
    if (isset($compiled[0], $compiled[1]) && $compiled[0] > $compiled[1]) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the minimum cannot exceed the maximum');
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileDate(array $params, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, 3, $field, $rule);
    if ($params === []) return [];

    $format = $this->toString($params[0], $field, $rule);
    $compiled = [$format];
    if ($format === '') {
      throw ValidationConfigurationException::forRule($field, $rule, 'the date format cannot be empty');
    }
    $min = \array_key_exists(1, $params) && $params[1] !== null ? $this->toString($params[1], $field, $rule) : null;
    $max = \array_key_exists(2, $params) && $params[2] !== null ? $this->toString($params[2], $field, $rule) : null;
    if (\array_key_exists(1, $params)) $compiled[] = $min;
    if (\array_key_exists(2, $params)) $compiled[] = $max;
    $minDate = $min !== null ? $this->parseExactDate($min, $format, $field, $rule) : null;
    $maxDate = $max !== null ? $this->parseExactDate($max, $format, $field, $rule) : null;
    if ($minDate !== null && $maxDate !== null && $minDate > $maxDate) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the minimum date cannot exceed the maximum date');
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileTime(array $params, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, 2, $field, $rule);
    if ($params === []) return [];

    $min = $params[0] !== null ? $this->normalizeTime($this->toString($params[0], $field, $rule), $field, $rule) : null;
    $max = \array_key_exists(1, $params) && $params[1] !== null ? $this->normalizeTime($this->toString($params[1], $field, $rule), $field, $rule) : null;
    $compiled = [$min];
    if (\array_key_exists(1, $params)) $compiled[] = $max;
    if ($min !== null && $max !== null && $min > $max) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the minimum time cannot exceed the maximum time');
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileDatetime(array $params, string $field, string $rule):array {
    $this->assertParameterCount($params, 0, 2, $field, $rule);
    if ($params === []) return [];

    $minValue = $params[0] !== null ? $this->toString($params[0], $field, $rule) : null;
    $maxValue = \array_key_exists(1, $params) && $params[1] !== null ? $this->toString($params[1], $field, $rule) : null;
    $compiled = [$minValue];
    if (\array_key_exists(1, $params)) $compiled[] = $maxValue;
    $min = $minValue !== null ? $this->parseExactDate($minValue, 'Y-m-d H:i:s', $field, $rule) : null;
    $max = $maxValue !== null ? $this->parseExactDate($maxValue, 'Y-m-d H:i:s', $field, $rule) : null;
    if ($min !== null && $max !== null && $min > $max) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the minimum datetime cannot exceed the maximum datetime');
    }
    return $compiled;
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileChoice(array $params, string $form, string $field, string $rule):array {
    if ($form === 'string') {
      if ($params === []) {
        throw ValidationConfigurationException::forRule($field, $rule, 'at least one option is required');
      }
      return [$params, true];
    }

    if ($form === 'associative' && $params !== [] && !\is_array($params[0])) {
      return [$params, true];
    }

    $this->assertParameterCount($params, 1, 2, $field, $rule);
    if (!\is_array($params[0]) || !\array_is_list($params[0]) || $params[0] === []) {
      throw ValidationConfigurationException::forRule($field, $rule, 'choice options must be a non-empty list');
    }
    $strict = isset($params[1]) ? $this->toBool($params[1], false, $field, $rule) : true;
    return [$params[0], $strict];
  }

  /**
   * @param list<mixed> $params
   * @return list<mixed>
   */
  private function compileExactIntegers(array $params, int $count, string $form, string $field, string $rule):array {
    $this->assertParameterCount($params, $count, $count, $field, $rule);
    $compiled = [];
    foreach ($params as $param) {
      $value = $this->toInt($param, $form === 'string', $field, $rule);
      if ($value < 0) {
        throw ValidationConfigurationException::forRule($field, $rule, 'length parameters cannot be negative');
      }
      $compiled[] = $value;
    }
    if ($count === 2 && $compiled[0] > $compiled[1]) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the minimum cannot exceed the maximum');
    }
    return $compiled;
  }

  /** @param list<mixed> $params */
  private function assertParameterCount(array $params, int $minimum, int $maximum, string $field, string $rule):void {
    $count = \count($params);
    if ($count < $minimum) {
      throw ValidationConfigurationException::forRule($field, $rule, 'required parameters are missing');
    }
    if ($count > $maximum) {
      throw ValidationConfigurationException::forRule($field, $rule, 'too many parameters were supplied');
    }
  }

  private function assertNonNegativeRange(
    int $minimum,
    int $maximum,
    string $field,
    string $rule,
    bool $zeroMaximumIsUnbounded = false
  ):void {
    if ($minimum < 0 || $maximum < 0) {
      throw ValidationConfigurationException::forRule($field, $rule, 'length and count parameters cannot be negative');
    }
    if ($minimum > $maximum && !($zeroMaximumIsUnbounded && $maximum === 0)) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the minimum cannot exceed the maximum');
    }
  }

  private function toInt(mixed $value, bool $fromString, string $field, string $rule):int {
    if (\is_int($value)) return $value;
    if ($fromString && \is_string($value)) {
      $filtered = \filter_var($value, FILTER_VALIDATE_INT);
      if ($filtered !== false) return $filtered;
    }
    throw ValidationConfigurationException::forRule($field, $rule, 'an integer parameter has the wrong type');
  }

  private function toFloat(mixed $value, bool $fromString, string $field, string $rule):float {
    if (\is_float($value) || \is_int($value)) {
      $number = (float)$value;
      if (\is_finite($number)) return $number;
    }
    if ($fromString && \is_string($value) && \is_numeric($value)) {
      $number = (float)$value;
      if (\is_finite($number)) return $number;
    }
    throw ValidationConfigurationException::forRule($field, $rule, 'a numeric parameter has the wrong type');
  }

  private function toBool(mixed $value, bool $fromString, string $field, string $rule):bool {
    if (\is_bool($value)) return $value;
    if ($fromString && \is_string($value)) {
      return match (\strtolower($value)) {
        'true', '1' => true,
        'false', '0' => false,
        default => throw ValidationConfigurationException::forRule($field, $rule, 'a boolean parameter has the wrong type'),
      };
    }
    throw ValidationConfigurationException::forRule($field, $rule, 'a boolean parameter has the wrong type');
  }

  private function toString(mixed $value, string $field, string $rule):string {
    if (!\is_string($value)) {
      throw ValidationConfigurationException::forRule($field, $rule, 'a string parameter has the wrong type');
    }
    return $value;
  }

  /** @return list<string> */
  private function toStringList(mixed $value, string $field, string $rule):array {
    if (!\is_array($value) || !\array_is_list($value)) {
      throw ValidationConfigurationException::forRule($field, $rule, 'an array parameter must be a list of strings');
    }
    // Build the narrowed list by construction. Proving the element type
    // through flow analysis alone depends on the analyser version.
    $strings = [];
    foreach ($value as $entry) {
      if (!\is_string($entry)) {
        throw ValidationConfigurationException::forRule($field, $rule, 'an array parameter must be a list of strings');
      }
      $strings[] = $entry;
    }

    return $strings;
  }

  private function isPatternAlias(string $name):bool {
    return \in_array(\strtolower(\trim($name)), ['pattern', 'regex'], true);
  }

  /** @return array{string, int} */
  private function readRegex(string $source, int $offset, string $field, string $rule):array {
    $length = \strlen($source);
    $this->skipWhitespace($source, $offset);
    if ($offset >= $length) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the regular expression is missing');
    }

    $delimiter = $source[$offset];
    if (\preg_match('/[A-Za-z0-9\\\\\s]/', $delimiter) === 1) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the regular expression delimiter is invalid');
    }

    $pairs = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
    $closing = $pairs[$delimiter] ?? $delimiter;
    $depth = 1;
    $escaped = false;
    $position = $offset + 1;
    $closingPosition = null;

    while ($position < $length) {
      $character = $source[$position];
      if ($escaped) {
        $escaped = false;
        $position++;
        continue;
      }
      if ($character === '\\') {
        $escaped = true;
        $position++;
        continue;
      }
      if ($delimiter !== $closing && $character === $delimiter) {
        $depth++;
      } elseif ($character === $closing) {
        $depth--;
        if ($depth === 0) {
          $closingPosition = $position;
          break;
        }
      }
      $position++;
    }

    if ($closingPosition === null) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the regular expression is unclosed');
    }

    $position = $closingPosition + 1;
    $modifierStart = $position;
    while ($position < $length && \ctype_alpha($source[$position])) {
      $position++;
    }
    $modifiers = \substr($source, $modifierStart, $position - $modifierStart);
    foreach (\str_split($modifiers) as $modifier) {
      if (!\str_contains(self::PCRE_MODIFIERS . 'gy', $modifier)) {
        throw ValidationConfigurationException::forRule($field, $rule, 'the regular expression contains an unsupported modifier');
      }
    }

    $modifiers = \str_replace(['g', 'y'], '', $modifiers);
    $pattern = \substr($source, $offset, $closingPosition - $offset + 1) . $modifiers;
    $this->validatePcre($pattern, $field, $rule);
    $this->skipWhitespace($source, $position);

    if ($position < $length && $source[$position] !== '|') {
      throw ValidationConfigurationException::forRule($field, $rule, 'unexpected characters follow the regular expression');
    }

    return [$pattern, $position];
  }

  private function validatePcre(string $pattern, string $field, string $rule):void {
    $warning = false;
    \set_error_handler(static function () use (&$warning):bool {
      $warning = true;
      return true;
    });
    try {
      $result = \preg_match($pattern, '');
    } finally {
      \restore_error_handler();
    }

    if ($warning || $result === false) {
      throw ValidationConfigurationException::forRule($field, $rule, 'the regular expression is invalid');
    }
  }

  private function skipWhitespace(string $source, int &$offset):void {
    $length = \strlen($source);
    while ($offset < $length && \ctype_space($source[$offset])) {
      $offset++;
    }
  }

  private function parseExactDate(string $value, string $format, string $field, string $rule):\DateTimeImmutable {
    $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
    $errors = \DateTimeImmutable::getLastErrors();
    if (
      $date === false
      || (\is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
      || $date->format($format) !== $value
    ) {
      throw ValidationConfigurationException::forRule($field, $rule, 'a configured date or datetime bound is invalid');
    }
    return $date;
  }

  private function normalizeTime(string $value, string $field, string $rule):string {
    foreach (['!H:i:s', '!H:i', '!g:i:s a', '!g:i a'] as $format) {
      $candidate = \str_contains($format, ' a') ? \strtolower($value) : $value;
      $time = \DateTimeImmutable::createFromFormat($format, $candidate);
      $errors = \DateTimeImmutable::getLastErrors();
      if ($time !== false && (!\is_array($errors) || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
        $inputFormat = \substr($format, 1);
        if ($time->format($inputFormat) === $candidate) {
          return $time->format('H:i:s');
        }
      }
    }
    throw ValidationConfigurationException::forRule($field, $rule, 'a configured time bound is invalid');
  }
}
