<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext\PHPUnit;

use function implode;
use function sprintf;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * A PHPUnit constraint that passes when a CodeCloneMap is empty — i.e. the
 * scanned code contains no duplication above the configured thresholds.
 *
 * On failure it lists every offending clone as `path:line ↔ path:line (N lines)`,
 * with inconsistent (diverged Type-3) clones flagged, so the test output tells you
 * exactly what to extract — no second `phpcpd` run needed to find it.
 */
final class DuplicationConstraint extends Constraint
{
    #[\Override]
    public function toString(): string
    {
        return 'contains no duplicated code';
    }

    #[\Override]
    protected function matches(mixed $other): bool
    {
        return $other instanceof CodeCloneMap && $other->count() === 0;
    }

    #[\Override]
    protected function failureDescription(mixed $other): string
    {
        return 'the scanned code ' . $this->toString();
    }

    #[\Override]
    protected function additionalFailureDescription(mixed $other): string
    {
        if (!$other instanceof CodeCloneMap) {
            return '';
        }

        $lines = [];

        foreach ($other as $clone) {
            $lines[] = '  ' . $this->describeClone($clone);
        }

        return sprintf(
            '%d clone%s found:%s%s',
            $other->count(),
            $other->count() === 1 ? '' : 's',
            "\n",
            implode("\n", $lines),
        );
    }

    private function describeClone(CodeClone $clone): string
    {
        $where = [];

        foreach ($clone->files() as $file) {
            $where[] = $file->name() . ':' . $file->startLine();
        }

        return sprintf(
            '%s%d lines @ %s',
            $clone->isGapped() ? '[inconsistent] ' : '',
            $clone->numberOfLines(),
            implode(' ↔ ', $where),
        );
    }
}
