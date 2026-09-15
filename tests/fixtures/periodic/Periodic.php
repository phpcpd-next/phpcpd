<?php
declare(strict_types=1);
// A class of one-line accessors. Nothing here is a copy of anything
// else under raw matching: every method name and every key differs.
// Normalized, they all collapse to one repeating token sequence.

final class Periodic
{
    /** @var array<string, int> */
    private array $attributes = [];

    public function getAlpha(): int
    {
        return $this->attributes['alpha'] ?? -1;
    }

    public function getBravo(): int
    {
        return $this->attributes['bravo'] ?? -1;
    }

    public function getCharlie(): int
    {
        return $this->attributes['charlie'] ?? -1;
    }

    public function getDelta(): int
    {
        return $this->attributes['delta'] ?? -1;
    }

    public function getEcho(): int
    {
        return $this->attributes['echo'] ?? -1;
    }

    public function getFoxtrot(): int
    {
        return $this->attributes['foxtrot'] ?? -1;
    }

    public function getGolf(): int
    {
        return $this->attributes['golf'] ?? -1;
    }

    public function getHotel(): int
    {
        return $this->attributes['hotel'] ?? -1;
    }

    public function getIndia(): int
    {
        return $this->attributes['india'] ?? -1;
    }

    public function getJuliet(): int
    {
        return $this->attributes['juliet'] ?? -1;
    }

    public function getKilo(): int
    {
        return $this->attributes['kilo'] ?? -1;
    }

    public function getLima(): int
    {
        return $this->attributes['lima'] ?? -1;
    }
}
