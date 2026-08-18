<?php

declare(strict_types=1);

namespace Demo\Context;

use Demo\Context\CommaAliased as CommaShort, Demo\Context\CommaSkipped as CommaUnused;
use Demo\Context\{BracedAliased as BracedShort, BracedSkipped as BracedUnused};

use function Demo\Context\aliased_helper as short_helper;

final class AliasedImportForms
{
    public function run(): void
    {
        new CommaShort();
        new BracedShort();
        short_helper();
    }
}
