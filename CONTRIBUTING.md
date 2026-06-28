# Contributing to phpcpd-next

Thanks for your interest in improving phpcpd-next — a PHP 8.5+ fork of
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

Run the full local toolchain — all three must be green, exactly as CI enforces:

```bash
vendor/bin/phpunit            # tests: all green
vendor/bin/phpstan analyse    # static analysis: level max (10), zero errors
vendor/bin/php-cs-fixer fix    # code style: PER-CS2.0, no changes left
```

The project holds a hard quality bar:

- **PHPStan level `max`** (level 10 since PHPStan 2.0) with zero errors.
- **PER-CS 2.0** code style via PHP-CS-Fixer.
- **Tests must be useful, not bureaucratic** — cover real behaviour and edge
  cases, not trivial getters. See `tests/` for the existing style.

## Changes are documented in `MODERNIZATION.md`

This project doubles as a guide to modernising an archived PHP codebase. Every
non-trivial change is recorded in [`MODERNIZATION.md`](MODERNIZATION.md) as a numbered section
with a `diff` block and a **Why** line. If your change is substantive, add an
entry following the existing format (see "How to add a change entry" at the end
of that file).

## Reporting bugs and proposing features

Open an issue with a minimal reproduction (for bugs) or a clear motivation and
proposed CLI/behaviour (for features). For detector-algorithm proposals, the
research roadmap lives in [`ROADMAP.md`](ROADMAP.md) — check whether your idea is
already a planned item (or explicitly out of scope) before opening.

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
composer check                 # lint + analyse + test must all be green
composer validate              # composer.json must be valid
composer release 1.1.0         # bumps the VERSION constant (see bin/release.sh)
```

Then follow the steps the script prints: move the `[Unreleased]` CHANGELOG entries
under the new version heading, commit, and push a **SemVer tag** (`git tag -s v1.1.0`).
Packagist picks up the tag and publishes it. Verify with:

```bash
composer show phpcpd-next/phpcpd --all
```

The dist tarball is kept lean by `.gitattributes` (`export-ignore`): `tests/`,
`bench/`, `paper/`, and tool configs are not shipped, but `src/`, `integration/`,
and the `phpcpd` binary are.

## License

By contributing, you agree that your contributions are licensed under the
project's [BSD 3-Clause License](LICENSE), subject to the relicensing grant in
the [CLA](CLA.md).
