<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * ---------------------------------------------------------------------------
 * EXAMPLE — not part of the phpcpd-next test suite. Copy this into your own
 * project's tests/ directory to make duplication a build-breaking regression.
 * ---------------------------------------------------------------------------
 */

namespace App\Tests\Quality;

use LucianoPereira\PhpcpdNext\PHPUnit\AssertNoDuplication;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DuplicationExampleTest extends TestCase
{
    use AssertNoDuplication;

    #[Test]
    public function the_application_has_no_duplicated_code(): void
    {
        // Default engine (Rabin-Karp + TokenBag), default thresholds.
        $this->assertNoDuplication(__DIR__ . '/../../app');
    }

    #[Test]
    public function laravel_sources_are_dry(): void
    {
        // The 'laravel' preset scans app/routes/database/config from the project
        // root and skips vendor, storage, bootstrap/cache, Blade views, and the
        // migration boilerplate that is duplicate by design.
        $this->assertNoDuplication(preset: 'laravel', minTokens: 60);
    }

    #[Test]
    public function the_domain_layer_has_no_gapped_clones(): void
    {
        // The suffixtree engine also surfaces near-miss (Type-3) clones, which is
        // where a bug fixed in one copy but not its sibling tends to hide.
        $this->assertNoDuplication(
            __DIR__ . '/../../app/Domain',
            algorithm: 'suffixtree',
        );
    }
}
