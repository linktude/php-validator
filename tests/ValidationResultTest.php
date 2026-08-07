<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\ValidationException;
use TimeFrontiers\Validation\ValidationResult;

class ValidationResultTest extends TestCase {

  public function testPassingResultContract():void {
    $result = new ValidationResult(true, 'value', [], 'field');
    self::assertTrue($result->passes());
    self::assertFalse($result->fails());
    self::assertTrue($result->isValid());
    self::assertSame('value', $result->value());
    self::assertSame('field', $result->field());
    self::assertSame([], $result->errors());
    self::assertSame(0, $result->errorCount());
    self::assertSame($result, $result->throwIfFailed());
    self::assertSame(
      ['valid' => true, 'value' => 'value', 'field' => 'field', 'errors' => []],
      $result->toArray()
    );
  }

  public function testFailingResultErrorAccessors():void {
    $errors = ['field' => ['First.', 'Second.']];
    $result = new ValidationResult(false, null, $errors, 'field');
    self::assertTrue($result->fails());
    self::assertSame($errors, $result->errors());
    self::assertSame($errors['field'], $result->errorsFor('field'));
    self::assertTrue($result->hasError());
    self::assertTrue($result->hasError('field'));
    self::assertSame('First.', $result->first('field'));
    self::assertSame("'field': First.", $result->first());
    self::assertSame(["'field': First.", "'field': Second."], $result->messages());
    self::assertSame(2, $result->errorCount());
  }

  public function testThrowIfFailedPreservesErrorMapAnd422():void {
    $result = new ValidationResult(false, null, ['field' => ['Failed.']], 'field');
    try {
      $result->throwIfFailed();
      self::fail('Expected validation exception.');
    } catch (ValidationException $exception) {
      self::assertSame(422, $exception->getCode());
      self::assertSame(['field' => ['Failed.']], $exception->errors());
    }
  }
}
