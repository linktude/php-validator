<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\BulkValidationResult;
use TimeFrontiers\Validation\ValidationException;

class BulkValidationResultTest extends TestCase {

  public function testClassAutoloadsDirectlyAndExposesStableArrayShape():void {
    $result = new BulkValidationResult(true, ['field' => 'value'], []);
    self::assertTrue($result->passes());
    self::assertFalse($result->fails());
    self::assertTrue($result->isValid());
    self::assertSame(['field' => 'value'], $result->validated());
    self::assertSame('value', $result->get('field'));
    self::assertSame('fallback', $result->get('missing', 'fallback'));
    self::assertSame(0, $result->errorCount());
    self::assertSame(
      ['valid' => true, 'validated' => ['field' => 'value'], 'errors' => []],
      $result->toArray()
    );
  }

  public function testErrorAccessAndThrowingContract():void {
    $errors = ['field' => ['First.', 'Second.']];
    $result = new BulkValidationResult(false, [], $errors);
    self::assertSame($errors, $result->errors());
    self::assertSame($errors['field'], $result->errorsFor('field'));
    self::assertTrue($result->hasError());
    self::assertTrue($result->hasError('field'));
    self::assertSame("'field': First.", $result->first());
    self::assertSame('First.', $result->first('field'));
    self::assertSame(["'field': First.", "'field': Second."], $result->messages());
    self::assertSame(2, $result->errorCount());

    $this->expectException(ValidationException::class);
    $result->throwIfFailed();
  }
}
