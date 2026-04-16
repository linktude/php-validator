<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Validation rules.
 *
 * Each method returns [bool $valid, mixed $sanitized_value, ?string $error_message].
 * If valid, error_message is null. If invalid, sanitized_value may be null.
 */
class Rules {

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
  public static function name(
    mixed $value,
    array $restricted = [],
    int $min_length = 2,
    int $max_length = 35
  ):array {
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
  public static function username(
    mixed $value,
    int $min_length = 3,
    int $max_length = 32,
    array $restricted = [],
    string $case = 'UPPER',
    array $allowed_chars = []
  ):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    // Build regex
    $regex = '/^[a-zA-Z0-9';
    foreach ($allowed_chars as $char) {
      $regex .= '\\' . $char;
    }
    $regex .= ']+$/';

    if (!\preg_match($regex, $value)) {
      $msg = 'Must contain only letters and numbers';
      if (!empty($allowed_chars)) {
        $msg .= ', and: ' . \implode(' ', $allowed_chars);
      }
      return [false, null, $msg . '.'];
    }

    $len = \mb_strlen($value);
    if ($len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    foreach ($restricted as $word) {
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
  public static function password(
    mixed $value,
    int $min_length = 8,
    int $max_length = 128,
    bool $require_upper = true,
    bool $require_lower = true,
    bool $require_number = true,
    bool $require_special = true
  ):array {
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
  public static function phone(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \preg_replace('/\s+/', '', \trim($value));

    if (!\preg_match('/^\+[1-9]\d{5,14}$/', $value)) {
      return [false, null, 'Invalid phone number. Use E.164 format: +[country code][number].'];
    }

    return [true, $value, null];
  }

  /**
   * Alias for phone().
   */
  public static function tel(mixed $value):array {
    return self::phone($value);
  }

  /**
   * Validate URL.
   */
  public static function url(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);
    $filtered = \filter_var($value, FILTER_VALIDATE_URL);

    if ($filtered === false) {
      return [false, null, 'Invalid URL. Include protocol (http://, https://, etc.).'];
    }

    return [true, $filtered, null];
  }

  /**
   * Validate IP address.
   *
   * @param mixed $value
   * @param string $version 'v4', 'v6', or 'any'.
   */
  public static function ip(mixed $value, string $version = 'any'):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    $flag = match (\strtolower($version)) {
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
  public static function text(
    mixed $value,
    int $min_length = 0,
    int $max_length = 0
  ):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);
    $sanitized = \htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $len = \mb_strlen($value);
    if ($min_length > 0 && $len < $min_length) {
      return [false, null, "Must be at least {$min_length} characters."];
    }
    if ($max_length > 0 && $len > $max_length) {
      return [false, null, "Must not exceed {$max_length} characters."];
    }

    return [true, $sanitized, null];
  }

  /**
   * Validate HTML content.
   */
  public static function html(
    mixed $value,
    int $min_length = 0,
    int $max_length = 0,
    array $allowed_tags = []
  ):array {
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
  public static function slug(
    mixed $value,
    int $min_length = 1,
    int $max_length = 128
  ):array {
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
  public static function hex(mixed $value, int $length = 0):array {
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
  public static function alpha(
    mixed $value,
    int $min_length = 0,
    int $max_length = 0
  ):array {
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
  public static function alphanumeric(
    mixed $value,
    int $min_length = 0,
    int $max_length = 0
  ):array {
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
   */
  public static function pattern(mixed $value, string $regex):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    // Validate regex syntax
    if (@\preg_match($regex, '') === false) {
      return [false, null, 'Invalid pattern.'];
    }

    if (!\preg_match($regex, $value)) {
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
  public static function integer(
    mixed $value,
    ?int $min = null,
    ?int $max = null
  ):array {
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
  public static function int(mixed $value, ?int $min = null, ?int $max = null):array {
    return self::integer($value, $min, $max);
  }

  /**
   * Validate float/decimal.
   */
  public static function float(
    mixed $value,
    ?float $min = null,
    ?float $max = null
  ):array {
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
  public static function decimal(mixed $value, ?float $min = null, ?float $max = null):array {
    return self::float($value, $min, $max);
  }

  /**
   * Validate boolean.
   */
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
  public static function date(
    mixed $value,
    string $format = 'Y-m-d',
    ?string $min = null,
    ?string $max = null
  ):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    // Try to parse with DateTime
    $parsed = \DateTime::createFromFormat($format, $value);
    if (!$parsed || $parsed->format($format) !== $value) {
      // Try flexible parsing
      try {
        $parsed = new \DateTime($value);
      } catch (\Exception $e) {
        return [false, null, 'Invalid date.'];
      }
    }

    $result = $parsed->format('Y-m-d');
    $timestamp = $parsed->getTimestamp();

    if ($min !== null) {
      $minTime = \strtotime($min);
      if ($minTime && $timestamp < $minTime) {
        return [false, null, "Date must be on or after {$min}."];
      }
    }

    if ($max !== null) {
      $maxTime = \strtotime($max);
      if ($maxTime && $timestamp > $maxTime) {
        return [false, null, "Date must be on or before {$max}."];
      }
    }

    return [true, $result, null];
  }

  /**
   * Validate time.
   *
   * @param mixed $value Time string.
   * @param string|null $min Minimum time (HH:MM:SS).
   * @param string|null $max Maximum time (HH:MM:SS).
   * @return array [valid, value, error]
   */
  public static function time(
    mixed $value,
    ?string $min = null,
    ?string $max = null
  ):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    // Parse time
    $isPM = \stripos($value, 'pm') !== false;
    $isAM = \stripos($value, 'am') !== false;
    $value = \preg_replace('/[^0-9:]/', '', $value);

    \preg_match_all('/\d+/', $value, $matches);
    $parts = $matches[0] ?? [];

    if (empty($parts)) {
      return [false, null, 'Invalid time format.'];
    }

    $hour = (int)($parts[0] ?? 0);
    $minute = (int)($parts[1] ?? 0);
    $second = (int)($parts[2] ?? 0);

    // Convert 12-hour to 24-hour
    if ($isPM && $hour < 12) {
      $hour += 12;
    } elseif ($isAM && $hour === 12) {
      $hour = 0;
    }

    if ($hour > 23 || $minute > 59 || $second > 59) {
      return [false, null, 'Invalid time.'];
    }

    $result = \sprintf('%02d:%02d:%02d', $hour, $minute, $second);

    if ($min !== null && $result < $min) {
      return [false, null, "Time must be at or after {$min}."];
    }
    if ($max !== null && $result > $max) {
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
  public static function datetime(
    mixed $value,
    ?string $min = null,
    ?string $max = null
  ):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \trim($value);

    try {
      $parsed = new \DateTime($value);
    } catch (\Exception $e) {
      return [false, null, 'Invalid datetime.'];
    }

    $result = $parsed->format('Y-m-d H:i:s');
    $timestamp = $parsed->getTimestamp();

    if ($min !== null) {
      $minTime = \strtotime($min);
      if ($minTime && $timestamp < $minTime) {
        return [false, null, "Datetime must be on or after {$min}."];
      }
    }

    if ($max !== null) {
      $maxTime = \strtotime($max);
      if ($maxTime && $timestamp > $maxTime) {
        return [false, null, "Datetime must be on or before {$max}."];
      }
    }

    return [true, $result, null];
  }

  // =========================================================================
  // Choice Validations
  // =========================================================================

  /**
   * Validate value is in a set of options.
   */
  public static function in(mixed $value, array $options, bool $strict = true):array {
    if (!\in_array($value, $options, $strict)) {
      return [false, null, 'Invalid option. Must be one of: ' . \implode(', ', $options) . '.'];
    }
    return [true, $value, null];
  }

  /**
   * Alias for in().
   */
  public static function option(mixed $value, array $options, bool $strict = true):array {
    return self::in($value, $options, $strict);
  }

  /**
   * Validate value is NOT in a set.
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
  public static function array(
    mixed $value,
    int $min_count = 0,
    int $max_count = 0
  ):array {
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
  public static function arrayOf(
    mixed $value,
    string $rule,
    array $rule_params = []
  ):array {
    if (!\is_array($value)) {
      return [false, null, 'Must be an array.'];
    }

    if (!\method_exists(self::class, $rule)) {
      return [false, null, "Unknown validation rule: {$rule}."];
    }

    $validated = [];
    foreach ($value as $index => $item) {
      $result = self::$rule($item, ...$rule_params);
      if (!$result[0]) {
        return [false, null, "Item {$index}: {$result[2]}"];
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
  public static function creditcard(mixed $value):array {
    if (!\is_string($value) && !\is_int($value)) {
      return [false, null, 'Must be a string or number.'];
    }

    $number = \preg_replace('/\D/', '', (string)$value);

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
  public static function fileExtension(mixed $value, array $allowed):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $ext = \strtolower(\pathinfo($value, PATHINFO_EXTENSION));
    $allowed = \array_map('strtolower', $allowed);

    if (!\in_array($ext, $allowed, true)) {
      return [false, null, 'Invalid file type. Allowed: ' . \implode(', ', $allowed) . '.'];
    }

    return [true, $value, null];
  }

  /**
   * Validate country code (ISO 3166-1 alpha-2).
   */
  public static function countryCode(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \strtoupper(\trim($value));

    if (!\preg_match('/^[A-Z]{2}$/', $value)) {
      return [false, null, 'Invalid country code. Use ISO 3166-1 alpha-2 format (e.g., US, GB, NG).'];
    }

    return [true, $value, null];
  }

  /**
   * Validate currency code (ISO 4217).
   */
  public static function currencyCode(mixed $value):array {
    if (!\is_string($value)) {
      return [false, null, 'Must be a string.'];
    }

    $value = \strtoupper(\trim($value));

    if (!\preg_match('/^[A-Z]{3}$/', $value)) {
      return [false, null, 'Invalid currency code. Use ISO 4217 format (e.g., USD, EUR, NGN).'];
    }

    return [true, $value, null];
  }

  // =========================================================================
  // Length/Size Validations
  // =========================================================================

  /**
   * Validate minimum length.
   */
  public static function minLength(mixed $value, int $min):array {
    $length = \is_array($value) ? \count($value) : \mb_strlen((string)$value);

    if ($length < $min) {
      return [false, null, "Must be at least {$min} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate maximum length.
   */
  public static function maxLength(mixed $value, int $max):array {
    $length = \is_array($value) ? \count($value) : \mb_strlen((string)$value);

    if ($length > $max) {
      return [false, null, "Must not exceed {$max} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate exact length.
   */
  public static function length(mixed $value, int $length):array {
    $actual = \is_array($value) ? \count($value) : \mb_strlen((string)$value);

    if ($actual !== $length) {
      return [false, null, "Must be exactly {$length} characters."];
    }

    return [true, $value, null];
  }

  /**
   * Validate length between min and max.
   */
  public static function lengthBetween(mixed $value, int $min, int $max):array {
    $length = \is_array($value) ? \count($value) : \mb_strlen((string)$value);

    if ($length < $min || $length > $max) {
      return [false, null, "Must be between {$min} and {$max} characters."];
    }

    return [true, $value, null];
  }
}
