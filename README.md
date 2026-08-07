# TimeFrontiers PHP Validator

Request and configuration-boundary validation for PHP 8.1 and later. The package provides a fluent single-field API and deterministic bulk validation while keeping error maps stable and value-free.

Domain objects must still enforce their own invariants. This package does not perform database integrity checks, payment authorization, HTML purification, upload inspection, or output-context encoding.

## Installation

```bash
composer require timefrontiers/php-validator:^1.1
```

The package requires PHP 8.1 or later and `ext-mbstring`.

## Entry points and result types

```php
use TimeFrontiers\Validation\BulkValidationResult;
use TimeFrontiers\Validation\ValidationConfigurationException;
use TimeFrontiers\Validation\ValidationException;
use TimeFrontiers\Validation\ValidationResult;
use TimeFrontiers\Validation\Validator;
```

| Entry point | Result |
|---|---|
| `Validator::field(string $field, mixed $value)` | `FieldValidator`; call `validate()` for `ValidationResult` |
| `Validator::make(array $data, array $rules, array $messages = [])` | `BulkValidationResult` |
| `Validator::validate(array $data, array $rules, array $messages = [])` | Validated values or `ValidationException` |

`ValidationResult::value()` is the single-field accessor. Bulk access uses `BulkValidationResult::validated()` and `get()`; there is deliberately no bulk `values()` method.

## Fluent single-field validation

```php
$result = Validator::field('email', $_POST['email'] ?? null)
  ->required()
  ->email()
  ->validate();

if ($result->passes()) {
  $email = $result->value();
} else {
  $message = $result->first('email');
}
```

`bail(true)`—the default—stops after the first failed rule. Use `bail(false)` to collect subsequent errors:

```php
$result = Validator::field('username', $value)
  ->bail(false)
  ->alpha()->message('Use letters only.')
  ->min(3)
  ->validate();
```

Custom callbacks must return exactly `[bool $valid, mixed $normalized, ?string $error]`. A callback must provide application-safe, value-free messages.

```php
$result = Validator::field('identifier', $value)
  ->custom(static function (mixed $value): array {
    if (!is_string($value) || !str_starts_with($value, 'TF-')) {
      return [false, null, 'Invalid identifier.'];
    }

    return [true, strtoupper($value), null];
  })
  ->validate();
```

Malformed callback tuples throw `LogicException` rather than becoming input-validation failures.

## Bulk validation grammar

### String form

Rules are pipe-separated. Parameters follow the first colon and are comma-separated.

```php
$result = Validator::make($_POST, [
  'name' => 'required|name:2,35',
  'email' => 'required|email',
  'age' => 'required|int:18,120',
  'status' => 'in:active,inactive,pending',
  'website' => 'nullable|url',
]);
```

`name:min,max` maps to `name([], min, max)`. String `in:a,b,c` and `notIn:a,b,c` treat the comma-separated values as options and use strict comparison.

### Array form

Use bare strings for parameterless rules and nested tuples for parameterized rules:

```php
$result = Validator::make($data, [
  'age' => ['required', ['int', 18, 120]],
  'tags' => [['array', 1, 5]],
  'status' => [['in', ['active', 'inactive'], true]],
  'name' => [['name', ['root', 'admin'], 2, 35]],
]);
```

Flat parameter arrays such as `['array', 1, 5]` are ambiguous and throw `ValidationConfigurationException`; no entry is silently discarded.

Associative rules remain available where their parameter list is clear:

```php
$result = Validator::make($data, [
  'age' => [
    'required' => [],
    'int' => [18, 120],
  ],
]);
```

Prefer nested tuples when a rule itself accepts an array, especially `in`, `notIn`, `name`, `username`, and `html`.

### Regular expressions

`pattern` and `regex` accept one complete delimited PCRE expression. Commas and pipes inside the expression are data, and a pipe after the closing modifiers starts the next validation rule.

```php
$result = Validator::make($data, [
  'code' => 'required|pattern:/^[A-Z]{2,28}$/D',
  'interval' => 'regex:/^(?:monthly|yearly)$/D|required',
  'csv' => 'pattern:~^[^,|]+(?:,[^,|]+)*$~u',
  'escaped' => 'pattern:/a\/[b|c]{1,3}/i',
]);
```

The delimiter must be non-alphanumeric, non-backslash, and non-whitespace. Invalid or unclosed patterns throw a configuration exception without exposing the pattern. JavaScript-only trailing `g` and `y` modifiers are removed for backward compatibility; all other modifiers must be supported PCRE modifiers.

## Required, nullable, and dotted fields

- `required` rejects `null`, `''`, and `[]`.
- `required` accepts `0`, `'0'`, and `false`.
- `nullable` skips the remaining rules for `null`, `''`, and `[]` and preserves that value.
- A field is optional only when its policy explicitly includes `nullable`.

Dot notation reads nested input, while the validated result remains keyed by the configured dotted field name:

```php
$result = Validator::make(
  ['user' => ['email' => 'USER@example.com']],
  ['user.email' => 'required|email'],
);

$result->validated(); // ['user.email' => 'user@example.com']
```

Only passing fields appear in `validated()`.

## Result and error contracts

Both result types and `ValidationException` expose errors as:

```text
array<string field, list<string message>>
```

### `ValidationResult`

```php
$result->passes();
$result->fails();
$result->isValid();
$result->value();
$result->field();
$result->errors();
$result->errorsFor('email');
$result->hasError('email');
$result->first('email');
$result->messages();
$result->errorCount();
$result->throwIfFailed();
$result->toArray(); // valid, value, field, errors
```

### `BulkValidationResult`

```php
$result->passes();
$result->fails();
$result->isValid();
$result->validated();
$result->get('email', $default);
$result->errors();
$result->errorsFor('email');
$result->hasError('email');
$result->first('email');
$result->messages();
$result->errorCount();
$result->throwIfFailed();
$result->toArray(); // valid, validated, errors
```

A bulk custom message is configured per field and replaces that field's collected rule messages:

```php
$result = Validator::make(
  $data,
  ['email' => 'required|email'],
  ['email' => 'Please provide a valid email address.'],
);
```

## Exceptions

Unknown rules, malformed grammar, invalid regular expressions, and incorrect bulk parameters throw `ValidationConfigurationException`, which extends `InvalidArgumentException`. Messages may identify the configured field and rule but never include the submitted value or full regex.

Valid policies that reject input populate the error map. `Validator::validate()`, `validateOrFail()`, and `throwIfFailed()` throw `ValidationException` with code `422`:

```php
try {
  $data = Validator::validate($_POST, ['email' => 'required|email']);
} catch (ValidationConfigurationException $exception) {
  // Developer/configuration defect: fix the policy.
} catch (ValidationException $exception) {
  $errors = $exception->errors();
  $first = $exception->first();
}
```

## Rule reference

| Category | Rules and aliases |
|---|---|
| Presence | `required`, `nullable` |
| Strings | `name`, `username`, `email`, `password`, `phone`/`tel`, `url`, `ip`, `text`, `html`, `slug`, `uuid`, `json`, `hex`, `color`, `alpha`, `alphanumeric`/`alnum`, `pattern`/`regex` |
| Numbers | `int`/`integer`, `float`/`decimal`/`number`, `boolean`/`bool` |
| Date/time | `date`, `time`, `datetime` |
| Choices | `in`/`option`, `notIn`/`not_in` |
| Arrays | `array`; fluent `arrayOf()` |
| Length | `min`, `max`, `length`, `between` |
| Special | `creditcard`, `countryCode`/`country_code`, `currencyCode`/`currency_code`; fluent `fileExtension()` |

`date($format, ...)` accepts only input and bounds that exactly match `$format`, rejects parse warnings, and normalizes passing values to `Y-m-d`.

`time()` accepts complete `H:i`, `H:i:s`, `g:i a`, or `g:i:s a` strings and normalizes to `H:i:s`. `datetime()` accepts exactly `Y-m-d H:i:s`. Timezone-aware domain policies belong in application value objects after validation.

`arrayOf()` can invoke only an explicit non-recursive allowlist of item-safe rules. It rejects `arrayOf` itself, `creditcard`, unknown methods, and malformed item-rule parameters.

## Normalization and security boundary

Several rules normalize values: whitespace may be trimmed, email is lowercased, booleans and numbers are typed, dates/times are normalized, and colors gain a canonical `#` prefix.

The following limitations are intentional:

- `text()` retains its legacy `htmlspecialchars()` transformation. Its output is not guaranteed safe for HTML, JavaScript, CSS, URLs, SQL, shell commands, logs, or any other sink. Encode for the actual output context and avoid decoding after validation.
- `html()` returns trimmed input unchanged when no tag list is provided. When tags are provided it uses `strip_tags()`, which does not sanitize attributes or URLs. Rich HTML requires a purpose-built allowlist sanitizer.
- `url()` accepts only HTTP and HTTPS by default. It does not authorize a destination or protect an HTTP client from SSRF.
- `fileExtension()` checks only the case-normalized filename suffix. Upload handling must also verify server-observed MIME/content and storage policy.
- `countryCode()` and `currencyCode()` normalize two- and three-letter shapes; they do not consult maintained ISO registries.
- `creditcard()` performs only a Luhn check and returns the normalized digits for backward compatibility. It is inappropriate for Linktude payment collection and does not make card handling PCI-compliant. Use hosted/tokenized flows through `timefrontiers/php-payment-platform`.

The package never performs HTML output encoding for a specific sink, SQL escaping, payment authorization, database uniqueness checks, provider verification, or business-policy enforcement.

## Upgrading from 1.0.x

The valid fluent API, `Validator::make()`, `Validator::validate()`, result methods, custom field messages, error maps, and dotted lookup remain compatible. Review these deliberate corrections:

1. Unknown rules now throw instead of being skipped.
2. Flat parameter arrays must become nested tuples.
3. Bulk access is `validated()` and `get()`; stale `values()` and `value($field)` examples never represented the source API.
4. Date, time, and datetime parsing is exact and may reject formerly flexible input.
5. URL validation rejects non-HTTP(S) schemes.
6. Regex rules use the deterministic delimited-expression lexer.
7. Choice failures no longer list configured options in user-facing messages.
8. Documentation no longer describes `text()`, `html()`, filename, code-shape, or Luhn checks as general security sanitization.

There is no legacy parser mode because it would retain fail-open behavior.

## Development

```bash
composer validate --strict
composer install
composer check
composer audit
```

The package does not commit a Composer lock file. Release verification must include a clean dependency resolution, PHPUnit on PHP 8.1 and the current supported runtime, PHPStan level max, syntax lint, and Composer audit.

## License

MIT
