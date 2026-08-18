<?php

declare(strict_types=1);

namespace Demo\Context;

$key = 1;

$filter = static function (mixed $subject) use ($key): bool {
    return only_called_inside_the_top_level_closure($subject, $key);
};
