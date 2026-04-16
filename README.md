# TimeFrontiers PHP Validator

Modern PHP validation library with fluent API and bulk validation support.

## Installation

```bash
composer require timefrontiers/php-validator
```

## Features

- Fluent, chainable API for single-field validation
- Bulk validation with string or array syntax
- Automatic value sanitization
- Custom error messages
- Nullable/optional field support
- 30+ built-in validation rules
- Custom rule support
- Exception-based validation

## Quick Start

### Single Field Validation (Fluent API)

```php
use TimeFrontiers\Validation\Validator;

$result = Validator::field('email', $_POST['email'])
  ->required()
  ->email()
  ->validate();

if ($result->passes()) {
  $email = $result->value(); // Sanitized email
} else {
  echo $result->first(); // First error message
}
```

### Bulk Validation

```php
// String syntax
$result = Validator::make($_POST, [
  'name'  => 'required|name',
  'email' => 'required|email',
  'age'   => 'required|int:18,120',
  'bio'   => 'text:0,500',
]);

// Array syntax
$result = Validator::make($_POST, [
  'name'  => ['required', 'name'],
  'email' => ['required', 'email'],
  'age'   => ['required', ['int', 18, 120]],
]);

if ($result->fails()) {
  $errors = $result->errors();
  // ['email' => ['Invalid email address.'], ...]
}

$validated = $result->validated();
// ['name' => 'John', 'email' => 'john@example.com', 'age' => 25]
```

### Validate or Throw

```php
try {
  $data = Validator::validate($_POST, [
    'email' => 'required|email',
  ]);
  // Use $data['email']
} catch (ValidationException $e) {
  echo $e->first();
  $allErrors = $e->errors();
}
```

## Validation Rules

### String Rules

| Rule | Description | Example |
|------|-------------|---------|
| `name` | Human name (letters, hyphens, apostrophes) | `name` or `name:2,35` |
| `username` | Alphanumeric with optional chars | `username:3,32` |
| `email` | Valid email address | `email` |
| `password` | Strong password | `password:8,128` |
| `phone` / `tel` | E.164 phone format | `phone` |
| `url` | Valid URL with protocol | `url` |
| `ip` | IPv4 or IPv6 address | `ip` or `ip:v4` |
| `text` | Plain text with length | `text:10,1000` |
| `html` | HTML content | `html:0,5000` |
| `slug` | URL-friendly slug | `slug:1,128` |
| `uuid` | UUID v4 format | `uuid` |
| `json` | Valid JSON string | `json` |
| `hex` | Hexadecimal string | `hex` or `hex:32` |
| `color` | Hex color code | `color` |
| `alpha` | Letters only | `alpha:2,50` |
| `alphanumeric` | Letters and numbers | `alphanumeric` |
| `pattern` / `regex` | Custom regex | `pattern:/^[A-Z]+$/` |

### Numeric Rules

| Rule | Description | Example |
|------|-------------|---------|
| `int` / `integer` | Integer with optional range | `int` or `int:1,100` |
| `float` / `decimal` | Float with optional range | `float:0.0,99.99` |
| `boolean` / `bool` | Boolean value | `boolean` |

### Date/Time Rules

| Rule | Description | Example |
|------|-------------|---------|
| `date` | Date with optional range | `date` or `date:Y-m-d,2020-01-01,2030-12-31` |
| `time` | Time (HH:MM:SS) | `time` or `time:09:00:00,17:00:00` |
| `datetime` | Datetime | `datetime` |

### Choice Rules

| Rule | Description | Example |
|------|-------------|---------|
| `in` / `option` | Value in list | `in:active,inactive,pending` |
| `notIn` | Value not in list | `notIn:banned,deleted` |

### Array Rules

| Rule | Description | Example |
|------|-------------|---------|
| `array` | Is array with count | `array:1,10` |
| `arrayOf` | Each item passes rule | (fluent only) |

### Special Rules

| Rule | Description | Example |
|------|-------------|---------|
| `creditcard` | Luhn algorithm check | `creditcard` |
| `countryCode` | ISO 3166-1 alpha-2 | `countryCode` |
| `currencyCode` | ISO 4217 | `currencyCode` |
| `fileExtension` | File extension check | (fluent only) |

### Modifier Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Must be present | `required` |
| `nullable` | Allow null/empty | `nullable` |
| `min` | Minimum length | `min:5` |
| `max` | Maximum length | `max:255` |
| `length` | Exact length | `length:10` |
| `between` | Length range | `between:5,20` |

## Fluent API Details

### Basic Chain

```php
$result = Validator::field('username', $value)
  ->required()
  ->username(min: 3, max: 20, case: 'LOWER', allowed_chars: ['.', '_'])
  ->validate();
```

### Nullable Fields

```php
$result = Validator::field('middle_name', $value)
  ->nullable()  // Skip validation if empty
  ->name()
  ->validate();
```

### Custom Messages

```php
$result = Validator::field('email', $value)
  ->required()->message('Please enter your email')
  ->email()->message('This email looks invalid')
  ->validate();
```

### Custom Rules

```php
$result = Validator::field('domain', $value)
  ->required()
  ->custom(function ($value) {
    if (!checkdnsrr($value, 'MX')) {
      return [false, null, 'Domain has no MX record'];
    }
    return [true, $value, null];
  })
  ->validate();
```

### Get Value Directly

```php
// Returns sanitized value or false
$email = Validator::field('email', $value)
  ->email()
  ->value();

if ($email === false) {
  // Validation failed
}
```

### Throw on Failure

```php
try {
  $email = Validator::field('email', $value)
    ->required()
    ->email()
    ->validateOrFail();
} catch (ValidationException $e) {
  // Handle error
}
```

## Bulk Validation Details

### String Syntax

```php
$result = Validator::make($data, [
  'name'     => 'required|name',
  'email'    => 'required|email',
  'age'      => 'int:18,120',
  'status'   => 'in:active,inactive',
  'website'  => 'nullable|url',
]);
```

### Array Syntax

```php
$result = Validator::make($data, [
  'name'  => ['required', 'name'],
  'age'   => ['required', ['int', 18, 120]],
  'tags'  => ['array', 1, 5],
]);
```

### Custom Messages

```php
$result = Validator::make($data, [
  'email' => 'required|email',
], [
  'email' => 'Please provide a valid email address',
]);
```

### Dot Notation

```php
$data = [
  'user' => [
    'email' => 'test@example.com',
  ],
];

$result = Validator::make($data, [
  'user.email' => 'required|email',
]);
```

### Result Methods

```php
$result->passes();        // bool - validation passed
$result->fails();         // bool - validation failed
$result->validated();     // array - all validated values
$result->get('email');    // mixed - single validated value
$result->errors();        // array - all errors
$result->errorsFor('email'); // array - errors for field
$result->hasError('email');  // bool - field has errors
$result->first();         // string|null - first error
$result->first('email');  // string|null - first error for field
$result->messages();      // array - flat list of all messages
$result->throwIfFailed(); // throws ValidationException
```

## Rule Details

### Password Rule

```php
// Fluent with all options
Validator::field('password', $value)
  ->password(
    min: 8,
    max: 128,
    upper: true,    // Require uppercase
    lower: true,    // Require lowercase
    number: true,   // Require digit
    special: true   // Require special char
  )
  ->validate();
```

### Username Rule

```php
Validator::field('username', $value)
  ->username(
    min: 3,
    max: 20,
    restricted: ['admin', 'root', 'system'],
    case: 'LOWER',  // UPPER, LOWER, or PRESERVE
    allowed_chars: ['.', '_', '-']
  )
  ->validate();
```

### Date Rule

```php
Validator::field('birth_date', $value)
  ->date(
    format: 'Y-m-d',
    min: '1900-01-01',
    max: '2010-12-31'
  )
  ->validate();
```

### Array Validation

```php
// Array of emails
Validator::field('emails', $value)
  ->array(min: 1, max: 5)
  ->arrayOf('email')
  ->validate();
```

## ValidationException

```php
try {
  $data = Validator::validate($_POST, $rules);
} catch (ValidationException $e) {
  $e->getMessage();   // First error message
  $e->errors();       // All errors
  $e->errorsFor('email'); // Errors for specific field
  $e->first();        // First error
  $e->getCode();      // 422
}
```

## Extending with Custom Rules

### Using Rules Class Directly

```php
use TimeFrontiers\Validation\Rules;

// All rules return [bool $valid, mixed $sanitized, ?string $error]
$result = Rules::email('test@example.com');
// [true, 'test@example.com', null]

$result = Rules::int('abc');
// [false, null, 'Must be an integer.']
```

## License

MIT
