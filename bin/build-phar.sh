#!/usr/bin/env bash
# Build a distributable phpcpd-next.phar into build/.
#
# The phar bundles src/ and locale/ — no runtime Composer dependencies, so this
# needs neither `composer install` nor a vendor/ directory, only PHP and a
# checkout. It is runnable directly:
# `php build/phpcpd-next.phar <dir>` or `./build/phpcpd-next.phar <dir>`.
#
# Usage: bash bin/build-phar.sh
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

command -v php >/dev/null 2>&1 || { echo "ERROR: php not found" >&2; exit 1; }

php -d phar.readonly=0 "$DIR/bin/build-phar.php" "$@"
