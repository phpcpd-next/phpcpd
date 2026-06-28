# phpcpd-next — PHPUnit integration

Turn copy/paste detection into a **regression test**. Instead of (or in addition
to) running `phpcpd` as a separate CI step, assert that your code is duplication-free
from inside your test suite — so a clone introduced in a pull request makes the build
red, with the offending locations printed in the failure message.

This is a thin layer over phpcpd-next's **headless mode** (`Phpcpd::detect()`): it runs
detection in-process, with no shelling out to the binary and no temp files.

## What's here

| File | Purpose |
|------|---------|
| `src/AssertNoDuplication.php` | A trait adding `assertNoDuplication(...)` to any `TestCase`. |
| `src/DuplicationConstraint.php` | The underlying PHPUnit `Constraint` (use it directly with `assertThat()` if you prefer). |
| `examples/DuplicationExampleTest.php` | Copy-paste starting points. |

## Usage

```php
use LucianoPereira\PhpcpdNext\PHPUnit\AssertNoDuplication;
use PHPUnit\Framework\TestCase;

final class DuplicationTest extends TestCase
{
    use AssertNoDuplication;

    public function test_app_is_dry(): void
    {
        $this->assertNoDuplication(__DIR__ . '/../app', minTokens: 70);
    }
}
```

### Signature

```php
$this->assertNoDuplication(
    string|array $paths = [],          // directory or list of directories to scan
    int $minTokens = 70,               // sensitivity: lower = stricter
    int $minLines  = 5,
    ?string $algorithm = null,         // null = Rabin-Karp + TokenBag; 'suffixtree' for gapped clones
    array $exclude = [],               // substring or glob patterns, e.g. '*.blade.php'
    array $suffixes = ['.php'],
    ?string $preset = null,            // a framework preset, e.g. 'laravel'
    string $message = '',
);
```

### With a preset

```php
// Scans app/routes/database/config and skips framework noise. No path needed.
$this->assertNoDuplication(preset: 'laravel', minTokens: 60);
```

### Failure output

```
Failed asserting that the scanned code contains no duplicated code.
2 clones found:
  18 lines @ app/Services/Billing.php:42 ↔ app/Services/Invoicing.php:71
  [inconsistent] 24 lines @ app/Http/Controllers/UserController.php:90 ↔ app/Http/Controllers/AdminController.php:88
```

`[inconsistent]` marks a diverged (Type-3) clone — the dangerous kind, where one
copy was patched and its sibling was not.

## Installing it in your project

phpcpd-next ships these classes under the `LucianoPereira\PhpcpdNext\PHPUnit\`
namespace via its **dev autoloader**, so once `phpcpd-next/phpcpd` is a `require-dev`
of your project the trait is already autoloaded — just `use` it.

> This directory doubles as the canonical example of the integration. phpcpd-next's
> own `tests/SelfDryTest.php` uses this exact trait to guarantee its `src/` stays
> duplication-free — the integration is dogfooded, not just documented.
