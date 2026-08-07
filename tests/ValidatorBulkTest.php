<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\BulkValidationResult;
use TimeFrontiers\Validation\ValidationConfigurationException;
use TimeFrontiers\Validation\ValidationException;
use TimeFrontiers\Validation\Validator;

class ValidatorBulkTest extends TestCase {

  public function testBulkValidationNormalizesAndReturnsOnlyPassingFields():void {
    $result = Validator::make(
      ['email' => ' USER@Example.COM ', 'age' => '25', 'bad' => 'x'],
      ['email' => 'required|email', 'age' => 'int:18,120', 'bad' => 'int']
    );

    self::assertInstanceOf(BulkValidationResult::class, $result);
    self::assertTrue($result->fails());
    self::assertSame(['email' => 'user@example.com', 'age' => 25], $result->validated());
    self::assertSame(['Must be an integer.'], $result->errorsFor('bad'));
  }

  /** @return iterable<string, array{mixed, bool}> */
  public static function requiredValueProvider():iterable {
    yield 'missing/null' => [null, false];
    yield 'empty string' => ['', false];
    yield 'empty array' => [[], false];
    yield 'zero string' => ['0', true];
    yield 'integer zero' => [0, true];
    yield 'false' => [false, true];
  }

  #[DataProvider('requiredValueProvider')]
  public function testRequiredSemantics(mixed $value, bool $passes):void {
    $result = Validator::make(['value' => $value], ['value' => 'required']);
    self::assertSame($passes, $result->passes());
  }

  public function testNullableSkipsRemainingRulesAndPreservesValue():void {
    foreach ([null, '', []] as $value) {
      $result = Validator::make(['value' => $value], ['value' => 'nullable|email']);
      self::assertTrue($result->passes());
      self::assertSame($value, $result->get('value'));
    }
  }

  public function testMissingFieldFailsRequired():void {
    $result = Validator::make([], ['email' => 'required|email']);
    self::assertTrue($result->fails());
    self::assertSame(['email'], \array_keys($result->errors()));
  }

  public function testDottedLookupUsesFlatConfiguredOutputKey():void {
    $result = Validator::make(
      ['user' => ['email' => 'USER@example.com']],
      ['user.email' => 'required|email']
    );
    self::assertSame(['user.email' => 'user@example.com'], $result->validated());
  }

  public function testCustomFieldMessageReplacesCollectedMessages():void {
    $result = Validator::make(
      ['email' => 'invalid'],
      ['email' => 'required|email'],
      ['email' => 'Please provide a valid email address.']
    );
    self::assertSame(['Please provide a valid email address.'], $result->errorsFor('email'));
  }

  public function testNameAndChoiceAdaptersWork():void {
    $result = Validator::make(
      ['name' => 'john', 'status' => '1', 'loose' => 1],
      [
        'name' => 'name:2,35',
        'status' => 'in:1,2',
        'loose' => [['in', ['1', '2'], false]],
      ]
    );
    self::assertTrue($result->passes());
    self::assertSame('John', $result->get('name'));
    self::assertSame(1, $result->get('loose'));
  }

  public function testRegexWithAlternationAndCommaQuantifierRunsInBulk():void {
    $result = Validator::make(
      ['code' => 'AB', 'interval' => 'monthly'],
      [
        'code' => 'required|pattern:/^[A-Z]{2,28}$/D',
        'interval' => 'pattern:/^(?:monthly|yearly)$/D|required',
      ]
    );
    self::assertTrue($result->passes());
  }

  public function testMalformedConfigurationFailsBeforeReturningAResult():void {
    $this->expectException(ValidationConfigurationException::class);
    Validator::make(
      ['safe' => 'ok', 'secret' => 'submitted-secret'],
      ['safe' => 'text', 'secret' => 'misspelled']
    );
  }

  public function testAmbiguousFlatRuleArrayFails():void {
    $this->expectException(ValidationConfigurationException::class);
    Validator::make(['tags' => ['one']], ['tags' => ['array', 1, 5]]);
  }

  public function testValidateReturnsValuesOrThrows422():void {
    self::assertSame(
      ['age' => 25],
      Validator::validate(['age' => '25'], ['age' => 'int:18,120'])
    );

    try {
      Validator::validate(['age' => 'nope'], ['age' => 'int']);
      self::fail('Expected validation failure.');
    } catch (ValidationException $exception) {
      self::assertSame(422, $exception->getCode());
      self::assertSame(['age' => ['Must be an integer.']], $exception->errors());
    }
  }
}
