<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\Rules;
use TimeFrontiers\Validation\ValidationConfigurationException;

class RulesNumericTest extends TestCase {

  public function testIntegerAndFloatNormalizeNumericStrings():void {
    self::assertSame([true, 42, null], Rules::int('42', 1, 100));
    self::assertSame([true, 4.25, null], Rules::float('4.25', 1.0, 5.0));
  }

  public function testNumericBoundsRejectOutOfRangeValues():void {
    self::assertFalse(Rules::integer(4, 5)[0]);
    self::assertFalse(Rules::decimal(6.0, null, 5.0)[0]);
  }

  /** @return iterable<string, array{mixed, bool}> */
  public static function booleanProvider():iterable {
    yield 'bool true' => [true, true];
    yield 'bool false' => [false, false];
    yield 'true text' => ['true', true];
    yield 'zero text' => ['0', false];
    yield 'yes text' => ['yes', true];
    yield 'integer zero' => [0, false];
    yield 'integer two' => [2, true];
  }

  #[DataProvider('booleanProvider')]
  public function testBooleanNormalization(mixed $input, bool $expected):void {
    self::assertSame([true, $expected, null], Rules::boolean($input));
  }

  public function testLengthRulesSupportStringsAndArrays():void {
    self::assertTrue(Rules::minLength('abcd', 4)[0]);
    self::assertTrue(Rules::maxLength([1, 2], 2)[0]);
    self::assertTrue(Rules::length('abc', 3)[0]);
    self::assertTrue(Rules::lengthBetween([1, 2], 1, 3)[0]);
  }

  public function testNumericRulesRejectInvalidTypes():void {
    self::assertFalse(Rules::integer('1.2')[0]);
    self::assertFalse(Rules::float('not-a-number')[0]);
    self::assertFalse(Rules::boolean(new \stdClass())[0]);
    self::assertFalse(Rules::length(new \stdClass(), 0)[0]);
  }

  public function testNumericAliasesDelegateToCanonicalRules():void {
    self::assertSame(Rules::integer('42'), Rules::int('42'));
    self::assertSame(Rules::float('4.2'), Rules::decimal('4.2'));
    self::assertSame(Rules::boolean('yes'), Rules::bool('yes'));
  }

  public function testInvalidNumericBoundsFailClosed():void {
    $this->expectException(ValidationConfigurationException::class);
    Rules::integer(5, 10, 1);
  }
}
