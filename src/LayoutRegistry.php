<?php

declare(strict_types=1);

namespace Cnab;

use Cnab\Exceptions\CnabException;
use Cnab\Schema\Layout;

/**
 * A named catalog of layouts.
 *
 * The library ships only public, didactic layouts; applications register their
 * own (including private, institution-specific maps) at runtime. Each entry
 * carries a human label and is grouped by a "family" derived from the line
 * length (CNAB200/240/400/444/550/800, ...).
 */
final class LayoutRegistry
{
    /** @var array<string, Layout> */
    private array $layouts = [];

    /** @var array<string, string> */
    private array $labels = [];

    public function register(string $id, Layout $layout, string $label = ''): self
    {
        $this->layouts[$id] = $layout;
        $this->labels[$id] = $label !== '' ? $label : $id;

        return $this;
    }

    public function has(string $id): bool
    {
        return isset($this->layouts[$id]);
    }

    public function get(string $id): Layout
    {
        return $this->layouts[$id]
            ?? throw new CnabException(sprintf('No layout registered under "%s".', $id));
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->layouts);
    }

    /**
     * Lightweight metadata for every registered layout, handy for building a
     * picker UI.
     *
     * @return list<array{id:string,label:string,lineLength:int,family:string}>
     */
    public function describe(): array
    {
        $out = [];

        foreach ($this->layouts as $id => $layout) {
            $out[] = [
                'id' => $id,
                'label' => $this->labels[$id],
                'lineLength' => $layout->lineLength,
                'family' => 'CNAB'.$layout->lineLength,
            ];
        }

        return $out;
    }
}
