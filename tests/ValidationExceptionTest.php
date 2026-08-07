<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\ValidationException;

class ValidationExceptionTest extends TestCase {

  public function testExceptionRetainsCodeAndErrorMap():void {
    $errors = ['email' => ['Invalid email address.']];
    $exception = new ValidationException('Validation failed', $errors);
    self::assertSame(422, $exception->getCode());
    self::assertSame($errors, $exception->errors());
    self::assertSame($errors['email'], $exception->errorsFor('email'));
    self::assertSame('Invalid email address.', $exception->first());
  }

  public function testFirstFallsBackToSafeExceptionMessage():void {
    $exception = new ValidationException('Validation failed');
    self::assertSame('Validation failed', $exception->first());
  }
}
