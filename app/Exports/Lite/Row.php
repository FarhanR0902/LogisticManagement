<?php
namespace App\Exports\Lite;

class Row
{
    /** @param Cell[] $cells */
    public function __construct(public array $cells = []) {}

    public static function fromValues(array $v, ?Style $s = null): self
    {
        return new self(array_map(fn($x) => new Cell($x, $s), $v));
    }
}