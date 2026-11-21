<?php
namespace App\Exports\Lite;

class Cell
{
    public function __construct(public mixed $value = null, public ?Style $style = null) {}

    public static function fromValue(mixed $v, ?Style $s = null): self
    {
        return new self($v, $s);
    }
}