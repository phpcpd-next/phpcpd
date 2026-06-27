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

## License

By contributing, you agree that your contributions are licensed under the
project's [BSD 3-Clause License](LICENSE), subject to the relicensing grant in
the [CLA](CLA.md).
