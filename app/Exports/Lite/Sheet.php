<?php
namespace App\Exports\Lite;

class Sheet
{
    public $fh;
    public int $rowNum = 0;

    public function __construct(public string $name, public string $tmp) {}

    public function setName(string $n): void
    {
        $this->name = $n;
    }
}