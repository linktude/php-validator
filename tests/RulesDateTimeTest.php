<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\Rules;
use TimeFrontiers\Validation\ValidationConfigurationException;

class RulesDateTimeTest extends TestCase {

  public function testDateHonorsTheConfiguredFormatExactly():void {
    self::assertSame([true, '2024-02-29', null], Rules::date('29/02/2024', 'd/m/Y'));
    self::assertFalse(Rules::date('2024-02-29', 'd/m/Y')[0]);
    self::assertFalse(Rules::date('31/02/2024', 'd/m/Y')[0]);
  }

  public function testInvalidDateBoundsAreConfigurationFailures():void {
    $this->expectException(ValidationConfigurationException::class);
    Rules::date('2024-01-01', 'Y-m-d', 'not-a-date');
  }

  /** @return iterable<string, array{string, string}> */
  public static function acceptedTimeProvider():iterable {
    yield 'hours and minutes' => ['09:05', '09:05:00'];
    yield 'with seconds' => ['23:59:58', '23:59:58'];
    yield '12 hour' => ['9:05 pm', '21:05:00'];
    yield '12 hour seconds' => ['12:05:03 AM', '00:05:03'];
  }

  #[DataProvider('acceptedTimeProvider')]
  public function testAcceptedTimeFormatsNormalize(string $input, string $expected):void {
    self::assertSame([true, $expected, null], Rules::time($input));
  }

  /** @return iterable<string, array{string}> */
  public static function rejectedTimeProvider():iterable {
    yield 'surrounding text' => ['meet at 09:00 please'];
    yield 'missing colon' => ['9pm'];
    yield 'overflow' => ['25:00'];
    yield 'extra component' => ['09:00:00:00'];
  }

  #[DataProvider('rejectedTimeProvider')]
  public function testTimeRejectsPermissiveGarbage(string $input):void {
    self::assertFalse(Rules::time($input)[0]);
  }

  public function testDatetimeUsesExactStableFormat():void {
    self::assertSame(
      [true, '2025-04-30 12:30:45', null],
      Rules::datetime('2025-04-30 12:30:45')
    );
    self::assertFalse(Rules::datetime('April 30 2025 12:30')[0]);
  }

  public function testDateTimeBoundsAreApplied():void {
    self::assertFalse(Rules::date('2024-01-01', 'Y-m-d', '2024-02-01')[0]);
    self::assertFalse(Rules::time('08:00', '09:00', '17:00')[0]);
    self::assertFalse(Rules::datetime('2024-01-01 00:00:00', '2024-02-01 00:00:00')[0]);
  }
}
