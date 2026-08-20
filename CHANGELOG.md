# Changelog

## 1.1.2 - 2026-08-14

### Fixed

- `RuleParser::toStringList()` and `Validator::_stringListParam()` build their
  narrowed `list<string>` by construction instead of relying on flow analysis
  to prove the element type after a validating loop. PHPStan 2.2.8 could infer
  it; PHPStan 2.2.0 could not, so the CI job that resolves the **lowest**
  allowed dependencies failed `composer check` while a local run against the
  newest 2.2.x passed. No runtime behaviour changes — both methods validated
  their input before and still do.

## 1.1.1 - 2026-08-14

### Requirements

- **Raised the minimum PHP version from 8.1 to 8.5**, matching the coordinated
  project floor. Despite the patch number this release is **not installable on
  PHP 8.1 through 8.4**; a consumer that cannot move to PHP 8.5 must pin
  `1.1.0` explicitly rather than `^1.1`. Treat this as a required-action item,
  not a routine patch upgrade.

### Fixed

- `Rules::arrayOf()` rejects an item-rule parameter arity mismatch instead of
  discarding it. PHP accepts surplus arguments to a userland method without
  raising `TypeError`, so `arrayOf('email', [999])` previously passed silently
  with the configured parameter thrown away — a configured constraint that
  never applied, which is precisely the failure mode the 1.1 line exists to
  eliminate. Over- and under-supplied parameter counts now raise
  `ValidationConfigurationException`. Correctly and partially parameterised
  item rules are unaffected.

### Changed

- CI runs the current PHP floor across highest and lowest resolved
  dependencies, replacing the previous 8.1/8.5 matrix.

### Added

- This changelog. Migration guidance continues to live in the README's
  "Upgrading from 1.0.x" section.

## 1.1.0 - 2026-08-13

The coordinated 1.1 correctness and security release. Fail-open configuration
became fail-closed and several ambiguous transformations were clarified.

### Compatibility-impacting corrections

- Unknown rules, empty rule names, malformed grammar, invalid regexes, and
  incorrect rule parameters throw `ValidationConfigurationException` instead of
  being silently skipped.
- Ambiguous flat parameter arrays such as `['array', 1, 5]` are rejected;
  parameterised rules use nested tuples such as `[['array', 1, 5]]`.
- `date()`, `time()`, and `datetime()` parse exactly and no longer fall back to
  flexible interpretation.
- `url()` accepts only `http` and `https` by default.
- Choice failures no longer list the configured options in user-facing
  messages.

### Fixed

- A dedicated delimited-expression lexer parses `pattern`/`regex` rules, so
  commas, pipes, escaped delimiters, character classes, and trailing modifiers
  inside an expression are data rather than rule separators.
- `name:min,max` maps to `name([], $min, $max)` instead of raising `TypeError`.
- `in`/`notIn` accept both the string form and a nested array form carrying an
  explicit strictness flag.
- `Rules::username()` builds its character class with delimiter-aware
  `preg_quote()`.
- An empty term in a restricted-word list is skipped rather than rejecting
  every value.
- `arrayOf()` calls only an explicit allowlist of item-safe rules.
- Custom callbacks that do not return `[bool, mixed, string|null]` raise
  `LogicException` as a developer contract failure.

### Changed

- `BulkValidationResult` moved to its own PSR-4 file, keeping the same fully
  qualified class name, so it autoloads without `Validator.php` being loaded
  first.
- `ext-mbstring` is declared in `composer.json`.
- Documentation no longer describes `text()`, `html()`, filename, code-shape,
  or Luhn checks as general security sanitization.
