<?php

declare(strict_types=1);

namespace Demo\Context;

use Demo\Context\Grammars\PostgresGrammar as QueryGrammar;

final class AliasedImport
{
    public function grammar(): QueryGrammar
    {
        return new QueryGrammar();
    }
}
