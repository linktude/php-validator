<?php

declare(strict_types=1);

namespace TimeFrontiers\Validation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\Validation\RuleParser;
use TimeFrontiers\Validation\ValidationConfigurationException;

class RuleParserTest extends TestCase {

  private RuleParser $parser;

  protected function setUp():void {
    $this->parser = new RuleParser();
  }

  public function testParsesBareAndParameterizedStringRules():void {
    self::assertSame(
      [
        ['name' => 'required', 'params' => []],
        ['name' => 'int', 'params' => [18, 120]],
      ],
      $this->parser->parse('required|int:18,120', 'age')
    );
  }

  public function testParsesCanonicalAndAssociativeArrayRules():void {
    self::assertSame(
      [
        ['name' => 'required', 'params' => []],
        ['name' => 'int', 'params' => [18, 120]],
      ],
      $this->parser->parse(['required', ['int', 18, 120]], 'age')
    );

    self::assertSame(
      [
        ['name' => 'required', 'params' => []],
        ['name' => 'int', 'params' => [18, 120]],
      ],
      $this->parser->parse(['required' => [], 'int' => [18, 120]], 'age')
    );
  }

  /** @return iterable<string, array{string, string}> */
  public static function bulkRuleProvider():iterable {
    yield 'required' => ['required', 'required'];
    yield 'nullable' => ['nullable', 'nullable'];
    yield 'name' => ['name:2,35', 'name'];
    yield 'username' => ['username:3,32', 'username'];
    yield 'email' => ['email', 'email'];
    yield 'password' => ['password:8,128', 'password'];
    yield 'phone' => ['phone', 'phone'];
    yield 'tel alias' => ['tel', 'phone'];
    yield 'url' => ['url', 'url'];
    yield 'ip' => ['ip:v4', 'ip'];
    yield 'text' => ['text:1,10', 'text'];
    yield 'html' => ['html:1,10', 'html'];
    yield 'slug' => ['slug:1,10', 'slug'];
    yield 'uuid' => ['uuid', 'uuid'];
    yield 'json' => ['json', 'json'];
    yield 'hex' => ['hex:8', 'hex'];
    yield 'color' => ['color', 'color'];
    yield 'alpha' => ['alpha:1,10', 'alpha'];
    yield 'alphanumeric' => ['alphanumeric:1,10', 'alphanumeric'];
    yield 'alnum alias' => ['alnum', 'alphanumeric'];
    yield 'pattern' => ['pattern:/^[a-z]+$/D', 'pattern'];
    yield 'regex alias' => ['regex:~^[a-z]+$~u', 'pattern'];
    yield 'int' => ['int:-2,4', 'int'];
    yield 'integer alias' => ['integer', 'int'];
    yield 'float' => ['float:-2.5,4.5', 'float'];
    yield 'decimal alias' => ['decimal', 'float'];
    yield 'number alias' => ['number', 'float'];
    yield 'boolean' => ['boolean', 'boolean'];
    yield 'bool alias' => ['bool', 'boolean'];
    yield 'date' => ['date', 'date'];
    yield 'time' => ['time', 'time'];
    yield 'datetime' => ['datetime', 'datetime'];
    yield 'in' => ['in:active,inactive', 'in'];
    yield 'option alias' => ['option:active,inactive', 'in'];
    yield 'notIn' => ['notIn:blocked', 'notIn'];
    yield 'not_in alias' => ['not_in:blocked', 'notIn'];
    yield 'array' => ['array:1,5', 'array'];
    yield 'creditcard' => ['creditcard', 'creditcard'];
    yield 'countryCode' => ['countryCode', 'countryCode'];
    yield 'country_code alias' => ['country_code', 'countryCode'];
    yield 'currencyCode' => ['currencyCode', 'currencyCode'];
    yield 'currency_code alias' => ['currency_code', 'currencyCode'];
    yield 'min' => ['min:1', 'min'];
    yield 'max' => ['max:10', 'max'];
    yield 'length' => ['length:5', 'length'];
    yield 'between' => ['between:1,5', 'between'];
  }

  #[DataProvider('bulkRuleProvider')]
  public function testEveryBulkRuleAndAliasHasAnExplicitCompilerMapping(string $rule, string $canonical):void {
    $parsed = $this->parser->parse($rule, 'field');
    self::assertSame($canonical, $parsed[0]['name']);
  }

  /** @return iterable<string, array{string}> */
  public static function regexProvider():iterable {
    yield 'comma quantifier' => ['/^[A-Z]{2,28}$/D'];
    yield 'alternation pipe' => ['/^(?:monthly|yearly)$/D'];
    yield 'commas pipes unicode' => ['~^[^,|]+(?:,[^,|]+)*$~u'];
    yield 'escaped delimiter and class' => ['/a\\/[b|c]{1,3}/i'];
  }

  #[DataProvider('regexProvider')]
  public function testPreservesDelimitedRegexExpressions(string $regex):void {
    $parsed = $this->parser->parse("pattern:{$regex}", 'policy');
    self::assertSame($regex, $parsed[0]['params'][0]);
  }

  public function testRegexCanBeFollowedByAnotherRule():void {
    $parsed = $this->parser->parse('pattern:/^(?:monthly|yearly)$/D|required', 'interval');
    self::assertSame(['pattern', 'required'], \array_column($parsed, 'name'));
  }

  public function testJavascriptOnlyModifiersAreRemoved():void {
    $parsed = $this->parser->parse('regex:/abc/giy', 'field');
    self::assertSame('/abc/i', $parsed[0]['params'][0]);
  }

  public function testNameStringShorthandMapsAroundRestrictedWordsParameter():void {
    $parsed = $this->parser->parse('name:2,35', 'name');
    self::assertSame([[], 2, 35], $parsed[0]['params']);
  }

  public function testChoiceFormsPreserveStrictnessContract():void {
    self::assertSame(
      [['active', 'inactive'], true],
      $this->parser->parse('in:active,inactive', 'status')[0]['params']
    );
    self::assertSame(
      [['active', 'inactive'], false],
      $this->parser->parse([['in', ['active', 'inactive'], false]], 'status')[0]['params']
    );
  }

  /** @return iterable<string, array{mixed}> */
  public static function malformedRuleProvider():iterable {
    yield 'unknown' => ['requried'];
    yield 'empty rule' => ['required||email'];
    yield 'trailing pipe' => ['required|'];
    yield 'missing parameter' => ['min'];
    yield 'extra parameter' => ['email:anything'];
    yield 'empty parameter' => ['int:1,'];
    yield 'wrong type' => [[['int', 'one', 2]]];
    yield 'ambiguous flat array' => [['array', 1, 5]];
    yield 'unclosed regex' => ['pattern:/[a-z]+'];
    yield 'invalid delimiter' => ['pattern:a[a-z]+a'];
    yield 'invalid pcre' => ['pattern:/[a-/'];
    yield 'unsupported modifier' => ['pattern:/abc/z'];
  }

  #[DataProvider('malformedRuleProvider')]
  public function testRejectsMalformedConfiguration(mixed $rules):void {
    $this->expectException(ValidationConfigurationException::class);
    $this->parser->parse($rules, 'field');
  }

  public function testConfigurationExceptionDoesNotExposeRegexPolicy():void {
    try {
      $this->parser->parse('pattern:/TOP-SECRET-[/', 'password');
      self::fail('Expected malformed regex to throw.');
    } catch (ValidationConfigurationException $exception) {
      self::assertStringNotContainsString('TOP-SECRET', $exception->getMessage());
    }
  }
}
