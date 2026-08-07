<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation;

/**
 * Thrown when a validation policy is unknown, ambiguous, or malformed.
 */
class ValidationConfigurationException extends \InvalidArgumentException {

  public static function forRule(string $field, string $rule, string $reason):self {
    $location = $field !== '' ? " for field '{$field}'" : '';
    return new self("Invalid validation rule '{$rule}'{$location}: {$reason}.");
  }
}
