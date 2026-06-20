<?php

declare(strict_types=1);

namespace Cnab;

use Cnab\Formatting\FieldCodec;

/**
 * Serializes a {@see CnabFile} back into a fixed-width string.
 *
 * Records are joined with CRLF (the CNAB convention) by default; pass a custom
 * separator — for instance "" for a pure 550-byte stream — when a counterparty
 * expects something else.
 */
final class Writer
{
    public function __construct(
        private readonly FieldCodec $codec = new FieldCodec,
        private readonly string $lineSeparator = "\r\n",
        private readonly bool $trailingSeparator = true,
    ) {}

    public function write(CnabFile $file): string
    {
        $lines = [];

        foreach ($file->records() as $record) {
            $line = '';

            foreach ($record->definition->fields as $name => $field) {
                $value = $record->has($name) ? $record->get($name) : null;
                $line .= $this->codec->encode($field, $value);
            }

            $lines[] = $line;
        }

        $output = implode($this->lineSeparator, $lines);

        if ($this->trailingSeparator && $lines !== []) {
            $output .= $this->lineSeparator;
        }

        return $output;
    }
}
