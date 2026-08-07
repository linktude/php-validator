<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Validation rules.
 *
 * Each method returns [bool $valid, mixed $normalized_value, ?string $error_message].
 * If valid, error_message is null. If invalid, normalized_value may be null.
 *
 * @phpstan-type RuleResult array{bool, mixed, string|null}
 */
class Rules {

  /** @var array<string, non-empty-string> */
  private const ARRAY_OF_RULES = [
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
    'integer' => 'integer',
    'int' => 'integer',
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
    'fileextension' => 'fileExtension',
    'countrycode' => 'countryCode',
    'country_code' => 'countryCode',
    'currencycode' => 'currencyCode',
    'currency_code' => 'currencyCode',
    'min' => 'minLength',
    'max' => 'maxLength',
    'length' => 'length',
    'between' => 'lengthBetween',
  ];

  // =========================================================================
  // String Validations
  // =========================================================================

  /**
   * Validate human name.
   *
   * @param mixed $value
   * @param array $restricted Restricted strings.
   * @param int $min_length Minimum length (default: 2).
   * @param int $max_length Maximum length (default: 35).
   * @return array [valid, value, error]
   */
  /**
   * @param list<string> $restricted
   * @return RuleResult
   */
  public static function name(
    mixed $value,
    array $restricted = [],
    int $min_length = 2,
    int $max_length = 35
  ):array {
    self::assertLengthRange('name', $min_length, $max_length, false);

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    if (!\preg_match("/^[a-zA-Z'-]+$/", $value)) {
      return [false, null, 'Must contain only letters, hyphens, and apostrophes.'];
    }

    $len = \mb_strlen($value);
    if ($len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    foreach ($restricted as $word) {
      if ($word === '') continue;
      if (\stripos($value, $word) !== false) {
        return [false, null, 'Contains restricted content.'];
      }
    }

    return [true, \ucfirst(\strtolower($value)), null];
  }

  /**
   * Validate username/unique ID.
   *
   * @param mixed $value
   * @param int $min_length Minimum length.
   * @param int $max_length Maximum length.
   * @param array $restricted Restricted strings.
   * @param string $case UPPER, LOWER, or PRESERVE.
   * @param array $allowed_chars Extra allowed characters.
   * @return array [valid, value, error]
   */
  /**
   * @param list<string> $restricted
   * @param list<string> $allowed_chars
   * @return RuleResult
   */
  public static function username(
    mixed $value,
    int $min_length = 3,
    int $max_length = 32,
    array $restricted = [],
    string $case = 'UPPER',
    array $allowed_chars = []
  ):array {
    self::assertLengthRange('username', $min_length, $max_length, false);
    if (!\in_array(\strtoupper($case), ['UPPER', 'UPPERCASE', 'LOWER', 'LOWERCASE', 'PRESERVE'], true)) {
      throw ValidationConfigurationException::forRule('', 'username', 'the username case policy is invalid');
    }
    foreach ($allowed_chars as $character) {
      if (\mb_strlen($character) !== 1) {
        throw ValidationConfigurationException::forRule('', 'username', 'allowed username characters must contain one character each');
      }
    }

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    // Build regex
    $regex = '/^[a-zA-Z0-9';
    foreach ($allowed_chars as $char) {
      $regex .= \preg_quote($char, '/');
    }
    $regex .= ']+$/';

    if (!\preg_match($regex, $value)) {
      return [false, null, 'Must contain only allowed username characters.'];
    }

    $len = \mb_strlen($value);
    if ($len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    foreach ($restricted as $word) {
      if ($word === '') continue;
      if (\stripos($value, $word) !== false) {
        return [false, null, 'Contains restricted content.'];
      }
    }

    // Apply case
    $result = match (\strtoupper($case)) {
      'UPPER', 'UPPERCASE' => \strtoupper($value),
      'LOWER', 'LOWERCASE' => \strtolower($value),
      default => $value,
    };

    return [true, $result, null];
  }

  /**
   * Validate email address.
   */
  /** @return RuleResult */
  public static function email(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);
    $filtered = \filter_var($value, FILTER_VALIDATE_EMAIL);

    if ($filtered === false) {
      return [false, null, 'Invalid email address.'];
    }

    return [true, \strtolower($filtered), null];
  }

  /**
   * Validate strong password.
   *
   * @param mixed $value
   * @param int $min_length Minimum length (default: 8).
   * @param int $max_length Maximum length (default: 128).
   * @param bool $require_upper Require uppercase letter.
   * @param bool $require_lower Require lowercase letter.
   * @param bool $require_number Require number.
   * @param bool $require_special Require special character.
   * @return array [valid, value, error]
   */
  /** @return RuleResult */
  public static function password(
    mixed $value,
    int $min_length = 8,
    int $max_length = 128,
    bool $require_upper = true,
    bool $require_lower = true,
    bool $require_number = true,
    bool $require_special = true
  ):array {
    self::assertLengthRange('password', $min_length, $max_length, false);

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $len = \strlen($value);
    if ($len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    $errors = [];
    if ($require_upper && !\preg_match('/[A-Z]/', $value)) {
      $errors[] = 'uppercase letter';
    }
    if ($require_lower && !\preg_match('/[a-z]/', $value)) {
      $errors[] = 'lowercase letter';
    }
    if ($require_number && !\preg_match('/[0-9]/', $value)) {
      $errors[] = 'number';
    }
    if ($require_special && !\preg_match('/[!@#$%^&*()_+\-=\[\]{};\':\"\\|,.<>\/?]/', $value)) {
      $errors[] = 'special character';
    }

    if (!empty($errors)) {
      return [false, null, 'Must contain at least one: ' . \implode(', ', $errors) . '.'];
    }

    return [true, $value, null];
  }

  /**
   * Validate phone number (E.164 format).
   */
  /** @return RuleResult */
  public static function phone(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \preg_replace('/\s+/', '', \trim($value));
    if ($value === null) {
      throw new \LogicException('Phone normalization failed.');
    }

    if (!\preg_match('/^\+[1-9]\d{5,14}$/', $value)) {
      return [false, null, 'Invalid phone number. Use E.164 format: +[country code][number].'];
    }

    return [true, $value, null];
  }

  /**
   * Alias for phone().
   */
  /** @return RuleResult */
  public static function tel(mixed $value):array {
    return self::phone($value);
  }

  /**
   * Validate URL.
   */
  /** @return RuleResult */
  public static function url(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);
    $filtered = \filter_var($value, FILTER_VALIDATE_URL);

    if ($filtered === false) {
      return [false, null, 'Invalid HTTP(S) URL.'];
    }

    $scheme = \strtolower((string)\parse_url($filtered, PHP_URL_SCHEME));
    $host = \parse_url($filtered, PHP_URL_HOST);
    if (!\in_array($scheme, ['http', 'https'], true) || !\is_string($host) || $host === '') {
      return [false, null, 'Invalid HTTP(S) URL.'];
    }

    return [true, $filtered, null];
  }

  /**
   * Validate IP address.
   *
   * @param mixed $value
   * @param string $version 'v4', 'v6', or 'any'.
   */
  /** @return RuleResult */
  public static function ip(mixed $value, string $version = 'any'):array {
    $normalizedVersion = \strtolower($version);
    if (!\in_array($normalizedVersion, ['any', 'v4', 'ipv4', 'v6', 'ipv6'], true)) {
      throw ValidationConfigurationException::forRule('', 'ip', 'the IP version is invalid');
    }

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    $flag = match ($normalizedVersion) {
      'v4', 'ipv4' => FILTER_FLAG_IPV4,
      'v6', 'ipv6' => FILTER_FLAG_IPV6,
      default => 0,
    };

    $filtered = \filter_var($value, FILTER_VALIDATE_IP, $flag);

    if ($filtered === false) {
      return [false, null, 'Invalid IP address.'];
    }

    return [true, $filtered, null];
  }

  /**
   * Validate plain text with length constraints.
   */
  /** @return RuleResult */
  public static function text(
    mixed $value,
    int $min_length = 0,
    int $max_length = 0
  ):array {
    self::assertLengthRange('text', $min_length, $max_length, true);

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);
    $normalized = \htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $len = \mb_strlen($value);
    if ($min_length > 0 && $len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($max_length > 0 && $len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    return [true, $normalized, null];
  }

  /**
   * Validate HTML content.
   */
  /**
   * @param list<string> $allowed_tags
   * @return RuleResult
   */
  public static function html(
    mixed $value,
    int $min_length = 0,
    int $max_length = 0,
    array $allowed_tags = []
  ):array {
    self::assertLengthRange('html', $min_length, $max_length, true);

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    $len = \mb_strlen($value);
    if ($min_length > 0 && $len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($max_length > 0 && $len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    // Strip disallowed tags if specified
    if (!empty($allowed_tags)) {
      $value = \strip_tags($value, $allowed_tags);
    }

    return [true, $value, null];
  }

  /**
   * Validate slug (URL-friendly string).
   */
  /** @return RuleResult */
  public static function slug(
    mixed $value,
    int $min_length = 1,
    int $max_length = 128
  ):array {
    self::assertLengthRange('slug', $min_length, $max_length, false);

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    if (!\preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
      return [false, null, 'Must be lowercase letters, numbers, and hyphens only.'];
    }

    $len = \mb_strlen($value);
    if ($len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate UUID (v4).
   */
  /** @return RuleResult */
  public static function uuid(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim(\strtolower($value));

    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
    if (!\preg_match($pattern, $value)) {
      return [false, null, 'Invalid UUID format.'];
    }

    return [true, $value, null];
  }

  /**
   * Validate JSON string.
   */
  /** @return RuleResult */
  public static function json(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);
    \json_decode($value);

    if (\json_last_error() !== JSON_ERROR_NONE) {
      return [false, null, 'Invalid JSON: ' . \json_last_error_msg()];
    }

    return [true, $value, null];
  }

  /**
   * Validate hex string.
   */
  /** @return RuleResult */
  public static function hex(mixed $value, int $length = 0):array {
    if ($length < 0) {
      throw ValidationConfigurationException::forRule('', 'hex', 'the length cannot be negative');
    }

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim(\strtolower($value));

    if (!\preg_match('/^[0-9a-f]+$/', $value)) {
      return [false, null, 'Must be a valid hexadecimal string.'];
    }

    if ($length > 0 && \strlen($value) !== $length) {
      return [false, null, "Must be exactly {$length} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate hex color code.
   */
  /** @return RuleResult */
  public static function color(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim(\strtolower($value));

    // Remove # prefix if present
    if (\str_starts_with($value, '#')) {
      $value = \substr($value, 1);
    }

    if (!\preg_match('/^[0-9a-f]{3}([0-9a-f]{3})?$/', $value)) {
      return [false, null, 'Invalid hex color. Use format: #RGB or #RRGGBB.'];
    }

    return [true, '#' . $value, null];
  }

  /**
   * Validate alphabetic string.
   */
  /** @return RuleResult */
  public static function alpha(
    mixed $value,
    int $min_length = 0,
    int $max_length = 0
  ):array {
    self::assertLengthRange('alpha', $min_length, $max_length, true);

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    if (!\preg_match('/^[a-zA-Z]+$/', $value)) {
      return [false, null, 'Must contain only letters.'];
    }

    $len = \mb_strlen($value);
    if ($min_length > 0 && $len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($max_length > 0 && $len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate alphanumeric string.
   */
  /** @return RuleResult */
  public static function alphanumeric(
    mixed $value,
    int $min_length = 0,
    int $max_length = 0
  ):array {
    self::assertLengthRange('alphanumeric', $min_length, $max_length, true);

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    if (!\preg_match('/^[a-zA-Z0-9]+$/', $value)) {
      return [false, null, 'Must contain only letters and numbers.'];
    }

    $len = \mb_strlen($value);
    if ($min_length > 0 && $len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($max_length > 0 && $len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate against regex pattern.
   *
   * Accepts a complete delimited PCRE pattern (for example /^[a-z]+$/i).
   * JavaScript-only trailing g and y modifiers are removed for compatibility.
   */
  /** @return RuleResult */
  public static function pattern(mixed $value, string $regex):array {
    $compiled = (new RuleParser())->parse([['pattern', $regex]]);
    $pcreRegex = $compiled[0]['params'][0];
    if (!\is_string($pcreRegex)) {
      throw new \LogicException('The pattern compiler returned an invalid rule specification.');
    }

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    if (!\preg_match($pcreRegex, $value)) {
      return [false, null, 'Does not match required pattern.'];
    }

    return [true, $value, null];
  }

  // =========================================================================
  // Numeric Validations
  // =========================================================================

  /**
   * Validate integer.
   */
  /** @return RuleResult */
  public static function integer(
    mixed $value,
    ?int $min = null,
    ?int $max = null
  ):array {
    self::assertNumericBounds('integer', $min, $max);

    $filtered = \filter_var($value, FILTER_VALIDATE_INT);

    if ($filtered === false) {
      return [false, null, 'Must be an integer.'];
    }

    if ($min !== null && $filtered < $min) {
      return [false, null, "Must be at least {$min}."];
    }
    if ($max !== null && $filtered > $max) {
      return [false, null, "Must not exceed {$max}."];
    }

    return [true, $filtered, null];
  }

  /**
   * Alias for integer().
   */
  /** @return RuleResult */
  public static function int(mixed $value, ?int $min = null, ?int $max = null):array {
    return self::integer($value, $min, $max);
  }

  /**
   * Validate float/decimal.
   */
  /** @return RuleResult */
  public static function float(
    mixed $value,
    ?float $min = null,
    ?float $max = null
  ):array {
    self::assertNumericBounds('float', $min, $max);

    $filtered = \filter_var($value, FILTER_VALIDATE_FLOAT);

    if ($filtered === false) {
      return [false, null, 'Must be a number.'];
    }

    if ($min !== null && $filtered < $min) {
      return [false, null, "Must be at least {$min}."];
    }
    if ($max !== null && $filtered > $max) {
      return [false, null, "Must not exceed {$max}."];
    }

    return [true, $filtered, null];
  }

  /**
   * Alias for float().
   */
  /** @return RuleResult */
  public static function decimal(mixed $value, ?float $min = null, ?float $max = null):array {
    return self::float($value, $min, $max);
  }

  /**
   * Validate boolean.
   */
  /** @return RuleResult */
  public static function boolean(mixed $value):array {
    if (\is_bool($value)) {
      return [true, $value, null];
    }

    if (\is_string($value)) {
      $lower = \strtolower(\trim($value));
      if (\in_array($lower, ['true', '1', 'yes', 'on'], true)) {
        return [true, true, null];
      }
      if (\in_array($lower, ['false', '0', 'no', 'off', ''], true)) {
        return [true, false, null];
      }
    }

    if (\is_int($value)) {
      return [true, $value !== 0, null];
    }

    return [false, null, 'Must be a boolean value.'];
  }

  /**
   * Alias for boolean().
   */
  /** @return RuleResult */
  public static function bool(mixed $value):array {
    return self::boolean($value);
  }

  // =========================================================================
  // Date/Time Validations
  // =========================================================================

  /**
   * Validate date.
   *
   * @param mixed $value Date string.
   * @param string $format Expected format (default: Y-m-d).
   * @param string|null $min Minimum date.
   * @param string|null $max Maximum date.
   * @return array [valid, value, error]
   */
  /** @return RuleResult */
  public static function date(
    mixed $value,
    string $format = 'Y-m-d',
    ?string $min = null,
    ?string $max = null
  ):array {
    if ($format === '') {
      throw ValidationConfigurationException::forRule('', 'date', 'the date format cannot be empty');
    }

    $minDate = $min !== null ? self::configuredDate($min, $format, 'date') : null;
    $maxDate = $max !== null ? self::configuredDate($max, $format, 'date') : null;
    if ($minDate !== null && $maxDate !== null && $minDate > $maxDate) {
      throw ValidationConfigurationException::forRule('', 'date', 'the minimum date cannot exceed the maximum date');
    }

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);
    $parsed = self::exactDate($value, $format);
    if ($parsed === null) {
      return [false, null, 'Invalid date.'];
    }

    if ($minDate !== null && $parsed < $minDate) {
      return [false, null, "Date must be on or after {$min}."];
    }
    if ($maxDate !== null && $parsed > $maxDate) {
      return [false, null, "Date must be on or before {$max}."];
    }

    return [true, $parsed->format('Y-m-d'), null];
  }

  /**
   * Validate time.
   *
   * @param mixed $value Time string.
   * @param string|null $min Minimum time (HH:MM:SS).
   * @param string|null $max Maximum time (HH:MM:SS).
   * @return array [valid, value, error]
   */
  /** @return RuleResult */
  public static function time(
    mixed $value,
    ?string $min = null,
    ?string $max = null
  ):array {
    $minTime = $min !== null ? self::configuredTime($min, 'time') : null;
    $maxTime = $max !== null ? self::configuredTime($max, 'time') : null;
    if ($minTime !== null && $maxTime !== null && $minTime > $maxTime) {
      throw ValidationConfigurationException::forRule('', 'time', 'the minimum time cannot exceed the maximum time');
    }

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $result = self::exactTime(\trim($value));
    if ($result === null) {
      return [false, null, 'Invalid time format.'];
    }

    if ($minTime !== null && $result < $minTime) {
      return [false, null, "Time must be at or after {$min}."];
    }
    if ($maxTime !== null && $result > $maxTime) {
      return [false, null, "Time must be at or before {$max}."];
    }

    return [true, $result, null];
  }

  /**
   * Validate datetime.
   *
   * @param mixed $value Datetime string.
   * @param string|null $min Minimum datetime.
   * @param string|null $max Maximum datetime.
   * @return array [valid, value, error]
   */
  /** @return RuleResult */
  public static function datetime(
    mixed $value,
    ?string $min = null,
    ?string $max = null
  ):array {
    $format = 'Y-m-d H:i:s';
    $minDate = $min !== null ? self::configuredDate($min, $format, 'datetime') : null;
    $maxDate = $max !== null ? self::configuredDate($max, $format, 'datetime') : null;
    if ($minDate !== null && $maxDate !== null && $minDate > $maxDate) {
      throw ValidationConfigurationException::forRule('', 'datetime', 'the minimum datetime cannot exceed the maximum datetime');
    }

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $parsed = self::exactDate(\trim($value), $format);
    if ($parsed === null) {
      return [false, null, 'Invalid datetime.'];
    }
    if ($minDate !== null && $parsed < $minDate) {
      return [false, null, "Datetime must be on or after {$min}."];
    }
    if ($maxDate !== null && $parsed > $maxDate) {
      return [false, null, "Datetime must be on or before {$max}."];
    }

    return [true, $parsed->format($format), null];
  }

  // =========================================================================
  // Choice Validations
  // =========================================================================

  /**
   * Validate value is in a set of options.
   */
  /**
   * @param list<mixed> $options
   * @return RuleResult
   */
  public static function in(mixed $value, array $options, bool $strict = true):array {
    if (!\in_array($value, $options, $strict)) {
      return [false, null, 'Invalid option.'];
    }
    return [true, $value, null];
  }

  /**
   * Alias for in().
   */
  /**
   * @param list<mixed> $options
   * @return RuleResult
   */
  public static function option(mixed $value, array $options, bool $strict = true):array {
    return self::in($value, $options, $strict);
  }

  /**
   * Validate value is NOT in a set.
   */
  /**
   * @param list<mixed> $options
   * @return RuleResult
   */
  public static function notIn(mixed $value, array $options, bool $strict = true):array {
    if (\in_array($value, $options, $strict)) {
      return [false, null, 'Invalid option.'];
    }
    return [true, $value, null];
  }

  // =========================================================================
  // Array Validations
  // =========================================================================

  /**
   * Validate array.
   */
  /** @return RuleResult */
  public static function array(
    mixed $value,
    int $min_count = 0,
    int $max_count = 0
  ):array {
    self::assertLengthRange('array', $min_count, $max_count, true);

    if (!\is_array($value)) {
      return [false, null, 'Must be an array.'];
    }

    $count = \count($value);
    if ($min_count > 0 && $count < $min_count) {
      return [false, null, "Must have at least {$min_count} items."];
    }
    if ($max_count > 0 && $count > $max_count) {
      return [false, null, "Must not exceed {$max_count} items."];
    }

    return [true, $value, null];
  }

  /**
   * Validate each array item.
   */
  /**
   * @param list<mixed> $rule_params
   * @return RuleResult
   */
  public static function arrayOf(
    mixed $value,
    string $rule,
    array $rule_params = []
  ):array {
    if (!\is_array($value)) {
      return [false, null, 'Must be an array.'];
    }

    $method = self::ARRAY_OF_RULES[\strtolower($rule)] ?? null;
    if ($method === null) {
      throw ValidationConfigurationException::forRule('', 'arrayOf', 'the configured item rule is not allowed');
    }

    $validated = [];
    $reflection = new \ReflectionMethod(self::class, $method);
    foreach ($value as $index => $item) {
      try {
        $result = $reflection->invokeArgs(null, [$item, ...$rule_params]);
      } catch (\TypeError $exception) {
        throw new ValidationConfigurationException(
          "Invalid validation rule 'arrayOf': the item-rule parameters do not match its signature.",
          0,
          $exception
        );
      }
      if (
        !\is_array($result)
        || !\array_is_list($result)
        || \count($result) !== 3
        || !\is_bool($result[0])
        || (!\is_string($result[2]) && $result[2] !== null)
      ) {
        throw new \LogicException('An array item rule returned an invalid validation tuple.');
      }
      if (!$result[0]) {
        return [false, null, 'An array item is invalid: ' . ($result[2] ?? 'Validation failed.')];
      }
      $validated[$index] = $result[1];
    }

    return [true, $validated, null];
  }

  // =========================================================================
  // Special Validations
  // =========================================================================

  /**
   * Validate credit card number (Luhn algorithm).
   */
  /** @return RuleResult */
  public static function creditcard(mixed $value):array {
    if (!\is_string($value) && !\is_int($value)) {
      return [false, null, 'Must be a string or number.'];
    }

    $number = \preg_replace('/\D/', '', (string)$value);
    if ($number === null) {
      throw new \LogicException('Card-number normalization failed.');
    }

    if (\strlen($number) < 13 || \strlen($number) > 19) {
      return [false, null, 'Invalid card number length.'];
    }

    // Luhn algorithm
    $sum = 0;
    $length = \strlen($number);
    $parity = $length % 2;

    for ($i = 0; $i < $length; $i++) {
      $digit = (int)$number[$i];
      if ($i % 2 === $parity) {
        $digit *= 2;
        if ($digit > 9) {
          $digit -= 9;
        }
      }
      $sum += $digit;
    }

    if ($sum % 10 !== 0) {
      return [false, null, 'Invalid card number.'];
    }

    return [true, $number, null];
  }

  /**
   * Validate file extension.
   */
  /**
   * @param list<mixed> $allowed
   * @return RuleResult
   */
  public static function fileExtension(mixed $value, array $allowed):array {
    if ($allowed === []) {
      throw ValidationConfigurationException::forRule('', 'fileExtension', 'at least one allowed suffix is required');
    }
    $normalizedAllowed = [];
    foreach ($allowed as $extension) {
      if (!\is_string($extension) || $extension === '') {
        throw ValidationConfigurationException::forRule('', 'fileExtension', 'allowed suffixes must be non-empty strings');
      }
      $normalizedAllowed[] = \strtolower($extension);
    }

    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $ext = \strtolower(\pathinfo($value, PATHINFO_EXTENSION));
    if (!\in_array($ext, $normalizedAllowed, true)) {
      return [false, null, 'Invalid filename extension.'];
    }

    return [true, $value, null];
  }

  /**
   * Normalize a two-letter country-code-shaped value.
   */
  /** @return RuleResult */
  public static function countryCode(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \strtoupper(\trim($value));

    if (!\preg_match('/^[A-Z]{2}$/', $value)) {
      return [false, null, 'Must use a two-letter country code format.'];
    }

    return [true, $value, null];
  }

  /**
   * Normalize a three-letter currency-code-shaped value.
   */
  /** @return RuleResult */
  public static function currencyCode(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \strtoupper(\trim($value));

    if (!\preg_match('/^[A-Z]{3}$/', $value)) {
      return [false, null, 'Must use a three-letter currency code format.'];
    }

    return [true, $value, null];
  }

  // =========================================================================
  // Length/Size Validations
  // =========================================================================

  /**
   * Validate minimum length.
   */
  /** @return RuleResult */
  public static function minLength(mixed $value, int $min):array {
    if ($min < 0) {
      throw ValidationConfigurationException::forRule('', 'min', 'the length cannot be negative');
    }

    $length = self::valueLength($value);
    if ($length === null) {
      return [false, null, 'Must be a scalar, stringable object, or array.'];
    }

    if ($length < $min) {
      return [false, null, "Must be at least {$min} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate maximum length.
   */
  /** @return RuleResult */
  public static function maxLength(mixed $value, int $max):array {
    if ($max < 0) {
      throw ValidationConfigurationException::forRule('', 'max', 'the length cannot be negative');
    }

    $length = self::valueLength($value);
    if ($length === null) {
      return [false, null, 'Must be a scalar, stringable object, or array.'];
    }

    if ($length > $max) {
      return [false, null, "Must not exceed {$max} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate exact length.
   */
  /** @return RuleResult */
  public static function length(mixed $value, int $length):array {
    if ($length < 0) {
      throw ValidationConfigurationException::forRule('', 'length', 'the length cannot be negative');
    }

    $actual = self::valueLength($value);
    if ($actual === null) {
      return [false, null, 'Must be a scalar, stringable object, or array.'];
    }

    if ($actual !== $length) {
      return [false, null, "Must be exactly {$length} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate length between min and max.
   */
  /** @return RuleResult */
  public static function lengthBetween(mixed $value, int $min, int $max):array {
    self::assertLengthRange('between', $min, $max, false);

    $length = self::valueLength($value);
    if ($length === null) {
      return [false, null, 'Must be a scalar, stringable object, or array.'];
    }

    if ($length < $min || $length > $max) {
      return [false, null, "Must be between {$min} and {$max} characters."];
    }

    return [true, $value, null];
  }

  private static function exactDate(string $value, string $format):?\DateTimeImmutable {
    $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
    $errors = \DateTimeImmutable::getLastErrors();
    if (
      $date === false
      || (\is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
      || $date->format($format) !== $value
    ) {
      return null;
    }

    return $date;
  }

  private static function configuredDate(string $value, string $format, string $rule):\DateTimeImmutable {
    $date = self::exactDate($value, $format);
    if ($date === null) {
      throw ValidationConfigurationException::forRule('', $rule, 'a configured bound is invalid');
    }

    return $date;
  }

  private static function exactTime(string $value):?string {
    foreach (['!H:i:s', '!H:i', '!g:i:s a', '!g:i a'] as $format) {
      $candidate = \str_contains($format, ' a') ? \strtolower($value) : $value;
      $time = \DateTimeImmutable::createFromFormat($format, $candidate);
      $errors = \DateTimeImmutable::getLastErrors();
      if (
        $time !== false
        && (!\is_array($errors) || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $time->format(\substr($format, 1)) === $candidate
      ) {
        return $time->format('H:i:s');
      }
    }

    return null;
  }

  private static function configuredTime(string $value, string $rule):string {
    $time = self::exactTime($value);
    if ($time === null) {
      throw ValidationConfigurationException::forRule('', $rule, 'a configured bound is invalid');
    }

    return $time;
  }

  private static function valueLength(mixed $value):?int {
    if (\is_array($value)) {
      return \count($value);
    }
    if (\is_string($value)) {
      return \mb_strlen($value);
    }
    if (\is_int($value) || \is_float($value) || \is_bool($value)) {
      return \mb_strlen((string)$value);
    }
    if ($value instanceof \Stringable) {
      return \mb_strlen((string)$value);
    }

    return null;
  }

  private static function assertLengthRange(string $rule, int $minimum, int $maximum, bool $zeroMaximumIsUnbounded):void {
    if ($minimum < 0 || $maximum < 0) {
      throw ValidationConfigurationException::forRule('', $rule, 'length and count parameters cannot be negative');
    }
    if ($minimum > $maximum && !($zeroMaximumIsUnbounded && $maximum === 0)) {
      throw ValidationConfigurationException::forRule('', $rule, 'the minimum cannot exceed the maximum');
    }
  }

  private static function assertNumericBounds(
    string $rule,
    int|float|null $minimum,
    int|float|null $maximum
  ):void {
    if (
      (\is_float($minimum) && !\is_finite($minimum))
      || (\is_float($maximum) && !\is_finite($maximum))
    ) {
      throw ValidationConfigurationException::forRule('', $rule, 'numeric bounds must be finite');
    }
    if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
      throw ValidationConfigurationException::forRule('', $rule, 'the minimum cannot exceed the maximum');
    }
  }
}
