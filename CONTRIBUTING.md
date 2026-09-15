# Contributing to phpcpd-next

Thanks for your interest in improving phpcpd-next — a PHP 8.4+ successor to
`sebastianbergmann/phpcpd`. This guide covers how to contribute and the one
piece of paperwork we require.

## Contributor License Agreement (required)

Before your first contribution can be merged, you must agree to the
[Individual Contributor License Agreement](CLA.md). It grants the project a broad
license to your contribution and preserves the maintainer's ability to relicense
or dual-license the project in the future. You keep copyright to your own work —
the CLA is a license, not an assignment.

**To sign:**

1. Sign off every commit: `git commit -s` (adds a `Signed-off-by` line).
2. On your first pull request, add this line to the PR description:
   > I have read the CLA Document and I hereby sign the CLA.
3. Add yourself to `CONTRIBUTORS.md` in the same PR.

Contributions without a CLA agreement cannot be merged, because they would
foreclose the project's future licensing options.

## Development setup

```bash
composer install
```

## Before you open a pull request

Run what CI runs. Every one of these exits non-zero on its own and prints its
own verdict:

```bash
composer validate --strict                              # manifest and lock agree
vendor/bin/php-cs-fixer fix --dry-run --diff            # code style
vendor/bin/phpstan analyse --memory-limit=1G            # src/, level max
vendor/bin/phpstan analyse -c phpstan-bench.neon        # bench/, level max
vendor/bin/phpunit                                      # tests
php bench/check-log-equivalence.php                     # reporters, byte for byte
php bench/check-locales.php                             # translations
php bench/sigil.php --check                             # documented facts
php bench/check-provenance.php                          # licence inventory
```

`bench/` has its own PHPStan config because it lists its files one by one; the
`src/` run does not cover it. If you add a file to `bench/`, add it to
`phpstan-bench.neon` as well.

The project holds a hard quality bar:

- **PHPStan level `max`** (level 10 since PHPStan 2.0) with zero errors.
- **Code style via PHP-CS-Fixer**, deliberately light: seventeen mechanical
  rules, no `@PSR-12`, `@Symfony` or `@PhpCsFixer` preset. Each rule was kept
  only where the tree already complied, so the gate lands green and can only
  report a regression. Growing the set is the same decision made the same way:
  add a rule, measure what it wants to change, take it only if the change is
  one somebody would defend.
- **Tests must be useful, not bureaucratic** — cover real behaviour and edge
  cases, not trivial getters. See `tests/` for the existing style.

## Where a change gets written down

Two places, and they are not the same thing:

- **`CHANGELOG.md`** — one line, in the order the change landed, saying what
  moved and for whom.
- **[`docs/release-notes.md`](docs/release-notes.md)** — the reasoning, with
  the measurements it rests on. This is where a number goes, so that the
  changelog line can stay a sentence.

[`docs/MODERNIZATION.md`](docs/MODERNIZATION.md) is **not** one of them, and no
longer takes entries. It was the inherited-surface inventory, and its job
finished when that inventory reached zero and the licence became MIT; it is now
a record of how that happened. `php bench/check-provenance.php` is what keeps
it true.

## Reporting bugs and proposing features

Open an issue with a minimal reproduction (for bugs) or a clear motivation and
proposed CLI/behaviour (for features). For detector-algorithm proposals, the
the roadmap lives in [`ROADMAP.md`](ROADMAP.md) — check whether your idea is
already planned, or declined with a reason, before opening. An item gets onto
that list by naming the number it moves and the benchmark that measures it; the
characterisations behind the planned items are in
[`docs/research/deferred-engine-work.md`](docs/research/deferred-engine-work.md). `docs/research/` is the working record generally: dated,
written when the work happened, and not revised afterwards.

## Releasing & publishing to Packagist

The package is published on Packagist as
[`phpcpd-next/phpcpd`](https://packagist.org/packages/phpcpd-next/phpcpd).

**One-time setup (maintainer):**

1. Sign in to [packagist.org](https://packagist.org) → **Submit** → paste the
   GitHub URL `https://github.com/phpcpd-next/phpcpd`.
2. Enable auto-updates: install the **Packagist** GitHub app on the repo (or add
   the Packagist webhook under *Settings → Webhooks*). New tags then publish
   automatically.

**Cutting a release:**

```bash
composer check                 # lint, PHPStan max on src/ and bench/, tests,
                               # documented facts, translations
composer validate --strict     # composer.json and composer.lock must agree
composer release 2.1.0         # bumps the VERSION constant (see bin/release.sh)
```

`composer check` is not the whole gate. `docs/internal/releasing.md` carries the
rest — the reporter goldens, the provenance inventory, and the benchmark checks
that need a corpus argument to mean anything.

Then follow the steps the script prints: date the CHANGELOG heading, commit,
and push a **signed tag** (`git tag -s v2.1`).
The version constant is full SemVer, while the tag drops a `.0` patch — the script
prints the exact tag to use.
Packagist picks up the tag and publishes it. Verify with:

```bash
composer show phpcpd-next/phpcpd --all
```

The dist tarball is kept lean by `.gitattributes` (`export-ignore`): `tests/`,
`bench/`, `docs/`, `assets/` and the tool configs are not shipped.

Two paths look like development files and are not. **`locale/` is runtime
data** — every sentence the tool prints is read from it, so an archive without
it cannot produce a report, a refusal, or its own help screen. **`integration/`
is in the production autoloader** (the PHPUnit integration). Neither is
export-ignored, and nothing that ships may be added to that list without
checking what reads it at runtime.

## License

By contributing, you agree that your contributions are licensed under the
project's [MIT License](LICENSE), subject to the relicensing grant in
the [CLA](CLA.md).
