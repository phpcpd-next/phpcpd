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

namespace LucianoPereira\PhpcpdNext\Orphan;

use function ltrim;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * One `prefix => directory` entry of a composer autoload map, able to answer the
 * only question this project asks of it: **which file would the autoloader load
 * this name from?**
 *
 * That question is what separates two files declaring the same class into a live
 * one and an unreachable copy. PSR-4 and PSR-0 both map a name to exactly one
 * path per rule, so a file sitting anywhere else declares a symbol the autoloader
 * can never reach through it — a structural fact about wiring, derived from what
 * the project itself declares, and never from what a path happens to be called.
 *
 * The two standards differ in one way that matters here and is easy to get
 * wrong: PSR-4 *strips* the matched prefix before mapping the remainder under the
 * directory, PSR-0 does not, and PSR-0 additionally reads underscores in the
 * class name as separators (`Twig_Node_Print` → `Twig/Node/Print.php`).
 */
final readonly class AutoloadRule
{
    public const string PSR_4 = 'psr-4';
    public const string PSR_0 = 'psr-0';

    /**
     * @param string $standard  self::PSR_4 or self::PSR_0
     * @param string $prefix    the namespace prefix exactly as composer.json
     *                          declares it; '' is the fallback rule, which
     *                          matches every name
     * @param string $directory the directory it maps to, without a trailing slash
     */
    public function __construct(
        public string $standard,
        public string $prefix,
        public string $directory,
    ) {}

    /**
     * The path the autoloader would load $fqn from under this rule, or null when
     * the rule does not cover the name.
     *
     * The returned path is not checked against the filesystem: the caller is
     * asking whether a *known* file is the one this name resolves to, and a
     * missing file is simply a name nothing declares.
     */
    public function pathFor(string $fqn): ?string
    {
        $fqn    = ltrim($fqn, '\\');
        $prefix = $this->matchablePrefix();

        if ($prefix !== '' && !str_starts_with($fqn, $prefix)) {
            return null;
        }

        $relative = $this->standard === self::PSR_0
            ? $this->psr0Path($fqn)
            : str_replace('\\', '/', substr($fqn, strlen($prefix)));

        if ($relative === '') {
            return null;
        }

        return $this->directory . '/' . $relative . '.php';
    }

    /**
     * PSR-0 keeps the whole name below the directory, and turns underscores in
     * the class name — never in the namespace — into separators.
     */
    private function psr0Path(string $fqn): string
    {
        $split     = strrpos($fqn, '\\');
        $namespace = $split === false ? '' : str_replace('\\', '/', substr($fqn, 0, $split)) . '/';
        $class     = $split === false ? $fqn : substr($fqn, $split + 1);

        return $namespace . str_replace('_', '/', $class);
    }

    /**
     * The prefix as a boundary-safe match. A prefix is conventionally written
     * with its trailing separator (`Demo\App\`, `Twig_`); one written without it
     * would otherwise match `Demo\Application` as well as `Demo\App\Thing`, which
     * is a claim about a namespace the project may not own at all.
     */
    private function matchablePrefix(): string
    {
        if ($this->prefix === '' || str_ends_with($this->prefix, '\\') || str_ends_with($this->prefix, '_')) {
            return $this->prefix;
        }

        return $this->prefix . '\\';
    }
}
