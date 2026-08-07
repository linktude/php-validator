<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\ValidationException;
use TimeFrontiers\Validation\Validator;

class FieldValidatorTest extends TestCase {

  public function testChainedRulesUseThePreviouslyNormalizedValue():void {
    $result = Validator::field('email', ' USER@Example.COM ')->required()->email()->validate();
    self::assertTrue($result->passes());
    self::assertSame('user@example.com', $result->value());
  }

  public function testBailTrueStopsAndBailFalseCollects():void {
    $stopped = Validator::field('value', '123')->alpha()->min(5)->validate();
    self::assertSame(1, $stopped->errorCount());

    $collected = Validator::field('value', '123')->bail(false)->alpha()->min(5)->validate();
    self::assertSame(2, $collected->errorCount());
  }

  public function testMessageOverridesOnlyThePreviousRule():void {
    $result = Validator::field('value', '123')
      ->bail(false)
      ->alpha()->message('Letters only.')
      ->min(5)
      ->validate();
    self::assertSame(['Letters only.', 'Must be at least 5 characters.'], $result->errorsFor('value'));
  }

  public function testMalformedCustomRuleTupleThrowsLogicException():void {
    $this->expectException(\LogicException::class);
    Validator::field('value', 'secret')->custom(static fn():array => [true])->validate();
  }

  public function testFailingCustomRuleMayUseGenericMessage():void {
    $result = Validator::field('value', 'secret')
      ->custom(static fn(mixed $value):array => [false, $value, null])
      ->validate();
    self::assertSame(['Validation failed.'], $result->errorsFor('value'));
  }

  public function testNullableReturnsTheOriginalEmptyValue():void {
    $result = Validator::field('value', [])->nullable()->arrayOf('email')->validate();
    self::assertTrue($result->passes());
    self::assertSame([], $result->value());
  }

  public function testValidateOrFailUsesValidationException():void {
    $this->expectException(ValidationException::class);
    Validator::field('email', 'invalid')->email()->validateOrFail();
  }
}
