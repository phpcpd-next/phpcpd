<?php

declare(strict_types=1);

namespace Demo\Context;

final class ClosureCapture
{
    public function run(int $key): callable
    {
        return static function (mixed $subject) use ($key): bool {
            return self::onlyCalledInsideTheClosure($subject, $key);
        };
    }

    private static function onlyCalledInsideTheClosure(mixed $subject, int $key): bool
    {
        return $subject === $key;
    }

    public function afterTheClosure(): void
    {
        $this->tail();
    }

    private function tail(): void
    {
    }
}
