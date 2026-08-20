<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\Rules;
use TimeFrontiers\Validation\ValidationConfigurationException;

class RulesSecurityTest extends TestCase {

  /** @return iterable<string, array{string, bool}> */
  public static function urlProvider():iterable {
    yield 'https' => ['https://example.com/path', true];
    yield 'http' => ['http://example.com', true];
    yield 'javascript' => ['javascript:alert(1)', false];
    yield 'data' => ['data:text/html;base64,AAAA', false];
    yield 'ftp' => ['ftp://example.com/file', false];
    yield 'missing host' => ['https:///path', false];
  }

  #[DataProvider('urlProvider')]
  public function testUrlSchemePolicy(string $url, bool $passes):void {
    self::assertSame($passes, Rules::url($url)[0]);
  }

  public function testUrlFailureDoesNotExposeCredentials():void {
    $url = 'ftp://private-user:private-token@example.com/file';
    $result = Rules::url($url);
    self::assertFalse($result[0]);
    self::assertStringNotContainsString('private-token', (string)$result[2]);
  }

  public function testPatternConfigurationFailureDoesNotExposePolicy():void {
    try {
      Rules::pattern('submitted-secret', '/PRIVATE-POLICY-[ /');
      self::fail('Expected pattern configuration failure.');
    } catch (ValidationConfigurationException $exception) {
      self::assertStringNotContainsString('PRIVATE-POLICY', $exception->getMessage());
      self::assertStringNotContainsString('submitted-secret', $exception->getMessage());
    }
  }

  public function testArrayOfUsesAnExplicitAllowlist():void {
    self::assertSame([true, ['a@example.com', 'b@example.com'], null], Rules::arrayOf(
      ['A@Example.com', 'B@Example.com'],
      'email'
    ));

    $this->expectException(ValidationConfigurationException::class);
    Rules::arrayOf([['a@example.com']], 'arrayOf', ['email']);
  }

  public function testArrayOfRejectsItemRuleParameterArityMismatch():void {
    // PHP accepts surplus arguments to a userland method without raising
    // TypeError, so an over-supplied parameter must be rejected explicitly or
    // the configured constraint is discarded in silence.
    try {
      Rules::arrayOf(['a@example.com'], 'email', [999]);
      self::fail('A surplus item-rule parameter must be rejected.');
    } catch (ValidationConfigurationException $exception) {
      self::assertStringContainsString('at most', $exception->getMessage());
    }

    // Correctly parameterised and partially parameterised item rules still work.
    self::assertFalse(Rules::arrayOf([5], 'int', [100, 200])[0]);
    self::assertTrue(Rules::arrayOf([150], 'int', [100, 200])[0]);
    self::assertTrue(Rules::arrayOf([150], 'int', [100])[0]);
    self::assertTrue(Rules::arrayOf(['a@example.com'], 'email')[0]);
  }

  public function testArrayOfFailureDoesNotExposeSubmittedArrayKeys():void {
    $result = Rules::arrayOf(['private-token-key' => 'invalid'], 'email');
    self::assertFalse($result[0]);
    self::assertStringNotContainsString('private-token-key', (string)$result[2]);
  }

  public function testFileExtensionIsCaseNormalizedSuffixOnly():void {
    self::assertTrue(Rules::fileExtension('archive.PDF', ['pdf'])[0]);
    self::assertFalse(Rules::fileExtension('archive.pdf.exe', ['pdf'])[0]);
  }

  public function testCountryAndCurrencyRulesAreFormatOnly():void {
    self::assertSame([true, 'ZZ', null], Rules::countryCode('zz'));
    self::assertSame([true, 'ZZZ', null], Rules::currencyCode('zzz'));
    self::assertFalse(Rules::countryCode('ZZZ')[0]);
    self::assertFalse(Rules::currencyCode('ZZ')[0]);
  }

  public function testChoiceFailureDoesNotExposeConfiguredTokens():void {
    $result = Rules::in('submitted', ['private-token']);
    self::assertFalse($result[0]);
    self::assertSame('Invalid option.', $result[2]);
    self::assertSame(Rules::in('a', ['a']), Rules::option('a', ['a']));
    self::assertFalse(Rules::notIn('a', ['a'])[0]);
  }

  public function testLuhnUsesPublishedTestNumberWithoutEchoingFailures():void {
    self::assertTrue(Rules::creditcard('4111111111111111')[0]);
    $result = Rules::creditcard('4111111111111112');
    self::assertFalse($result[0]);
    self::assertStringNotContainsString('4111111111111112', (string)$result[2]);
  }
}
