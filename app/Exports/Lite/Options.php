<?php
namespace App\Exports\Lite;

class Options
{
    public array $cols = [];   // [[from, to, width], ...]

    public function setColumnWidthForRange(float $width, int $from, int $to): void
    {
        $this->cols[] = [$from, $to, $width];
    }
}