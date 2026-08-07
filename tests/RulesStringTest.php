<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\Rules;
use TimeFrontiers\Validation\ValidationConfigurationException;

class RulesStringTest extends TestCase {

  public function testEmailIsTrimmedAndLowercased():void {
    self::assertSame([true, 'user@example.com', null], Rules::email(' USER@Example.COM '));
  }

  public function testNameIgnoresEmptyRestrictedTerms():void {
    self::assertSame([true, 'Alice', null], Rules::name(' alice ', ['', 'root']));
  }

  public function testUsernameQuotesRegexMetacharacters():void {
    $result = Rules::username('ab.-]^\\', 3, 20, [], 'PRESERVE', ['.', '-', ']', '^', '\\']);
    self::assertTrue($result[0]);
    self::assertSame('ab.-]^\\', $result[1]);
  }

  public function testTextAndHtmlLegacyTransformationsRemainStable():void {
    self::assertSame(
      [true, '&lt;b&gt;&quot;Hello&quot;&lt;/b&gt;', null],
      Rules::text(' <b>"Hello"</b> ')
    );
    self::assertSame(
      [true, '<p>Hello</p>alert(1)', null],
      Rules::html('<p>Hello</p><script>alert(1)</script>', 0, 0, ['<p>'])
    );
    self::assertSame(
      [true, '<script>alert(1)</script>', null],
      Rules::html('<script>alert(1)</script>')
    );
  }

  /** @return iterable<string, array{array{bool, mixed, string|null}, mixed}> */
  public static function normalizedRuleProvider():iterable {
    yield 'phone' => [Rules::phone(' +234 8012345678 '), '+2348012345678'];
    yield 'slug' => [Rules::slug('my-slug'), 'my-slug'];
    yield 'uuid' => [Rules::uuid('550e8400-e29b-41d4-a716-446655440000'), '550e8400-e29b-41d4-a716-446655440000'];
    yield 'json' => [Rules::json(' {"ok":true} '), '{"ok":true}'];
    yield 'hex' => [Rules::hex(' A0B1 '), 'a0b1'];
    yield 'color' => [Rules::color('ABC'), '#abc'];
    yield 'alpha' => [Rules::alpha('Alpha'), 'Alpha'];
    yield 'alphanumeric' => [Rules::alphanumeric('A123'), 'A123'];
  }

  /** @param array{bool, mixed, string|null} $result */
  #[DataProvider('normalizedRuleProvider')]
  public function testStringNormalizationRegression(array $result, mixed $expected):void {
    self::assertTrue($result[0]);
    self::assertSame($expected, $result[1]);
  }

  public function testPasswordFailureDoesNotEchoPassword():void {
    $password = 'raw-secret-value';
    $result = Rules::password($password);
    self::assertFalse($result[0]);
    self::assertStringNotContainsString($password, (string)$result[2]);
  }

  /** @return iterable<string, array{array{bool, mixed, string|null}}> */
  public static function rejectedStringRuleProvider():iterable {
    yield 'name' => [Rules::name('Alice1')];
    yield 'username' => [Rules::username('has space')];
    yield 'email' => [Rules::email('not-an-email')];
    yield 'password' => [Rules::password('weak')];
    yield 'phone' => [Rules::phone('0801234')];
    yield 'ip' => [Rules::ip('999.999.999.999')];
    yield 'text type' => [Rules::text([])];
    yield 'html type' => [Rules::html([])];
    yield 'slug' => [Rules::slug('Not A Slug')];
    yield 'uuid' => [Rules::uuid('not-a-uuid')];
    yield 'json' => [Rules::json('{broken')];
    yield 'hex' => [Rules::hex('xyz')];
    yield 'color' => [Rules::color('#12')];
    yield 'alpha' => [Rules::alpha('abc1')];
    yield 'alphanumeric' => [Rules::alphanumeric('abc-1')];
    yield 'pattern' => [Rules::pattern('abc1', '/^[a-z]+$/D')];
  }

  /** @param array{bool, mixed, string|null} $result */
  #[DataProvider('rejectedStringRuleProvider')]
  public function testStringRuleRejectionRegression(array $result):void {
    self::assertFalse($result[0]);
    self::assertNull($result[1]);
    self::assertIsString($result[2]);
  }

  public function testStringRuleAliasesDelegateToCanonicalRules():void {
    self::assertSame(Rules::phone('+2348012345678'), Rules::tel('+2348012345678'));
  }

  public function testInvalidFluentIpConfigurationFailsClosed():void {
    $this->expectException(ValidationConfigurationException::class);
    Rules::ip('127.0.0.1', 'typo');
  }

  public function testInvalidStringLengthConfigurationFailsClosed():void {
    $this->expectException(ValidationConfigurationException::class);
    Rules::slug('valid-slug', 10, 2);
  }
}
