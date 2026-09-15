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

namespace LucianoPereira\PhpcpdNext\Triage;

/**
 * Why one file was triaged out. Carried rather than counted, because ruling T's
 * fourth requirement is that triage is never silent: a file removed before
 * detection ever sees it has to be able to say, in one line, what removed it and
 * on what evidence.
 */
final readonly class TriageDecision
{
    /**
     * Not program text by the project's own default excludes, so it never
     * reached the rungs that judge program text — and, under ruling V, never
     * witnessed that anything else was alive either.
     */
    public const string DERIVED = 'derived';

    /** Nothing in the scanned project references anything this file declares. */
    public const string UNWIRED = 'unwired';

    /** Every name it declares is autoloaded from another file. */
    public const string SHADOWED = 'shadowed';

    /**
     * It declares only namespaces the project's manifest does not own, from
     * outside every directory that manifest wires: someone else's code, vendored
     * into the tree.
     */
    public const string FOREIGN = 'foreign';

    /**
     * @param string $detail the evidence, in the form the reader can check: the
     *                       wired file for a shadow, the namespaces nothing claims
     *                       for a foreign file
     */
    public function __construct(
        public string $file,
        public string $reason,
        public string $detail,
    ) {}
}
