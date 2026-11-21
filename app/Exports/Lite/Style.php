<?php
namespace App\Exports\Lite;

class Style
{
    public bool $bold = false;
    public ?string $color = null;
    public ?string $bg = null;
    public ?string $format = null;

    public function withFontBold(bool $b = true): static { $c = clone $this; $c->bold = $b; return $c; }
    public function withFontColor(string $v): static { $c = clone $this; $c->color = $v; return $c; }
    public function withBackgroundColor(string $v): static { $c = clone $this; $c->bg = $v; return $c; }
    public function withFormat(string $v): static { $c = clone $this; $c->format = $v; return $c; }

    public function sig(): string
    {
        return (int) $this->bold . '|' . $this->color . '|' . $this->bg . '|' . $this->format;
    }
}