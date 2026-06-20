<?php

declare(strict_types=1);

namespace Cnab;

use Cnab\Exceptions\ParseException;
use Cnab\Formatting\FieldCodec;
use Cnab\Schema\Layout;

/**
 * Reads a raw CNAB string into a {@see CnabFile}.
 *
 * Lines may be separated by CRLF/LF, or the input may be one uninterrupted
 * stream whose length is a multiple of the layout line length — both shapes
 * occur in the wild and are handled transparently.
 */
final class Parser
{
    public function __construct(
        private readonly FieldCodec $codec = new FieldCodec,
    ) {}

    public function parse(string $content, Layout $layout): CnabFile
    {
        $file = new CnabFile($layout);

        foreach ($this->splitLines($content, $layout) as $number => $line) {
            $file->add($this->parseLine($line, $layout, $number + 1));
        }

        return $file;
    }

    private function parseLine(string $line, Layout $layout, int $number): Record
    {
        if (strlen($line) !== $layout->lineLength) {
            throw new ParseException(sprintf(
                'Line %d has length %d, expected %d.',
                $number,
                strlen($line),
                $layout->lineLength,
            ));
        }

        $code = $layout->codeOf($line);

        if (! $layout->hasRecord($code)) {
            throw new ParseException(sprintf('Line %d has unknown record type "%s".', $number, $code));
        }

        $definition = $layout->record($code);
        $values = [];

        foreach ($definition->fields as $name => $field) {
            $raw = substr($line, $field->offset(), $field->length);
            $values[$name] = $this->codec->decode($field, $raw);
        }

        return new Record($definition, $values);
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $content, Layout $layout): array
    {
        $content = rtrim($content, "\r\n");

        if ($content === '') {
            return [];
        }

        if (str_contains($content, "\n")) {
            return array_map(
                static fn (string $line): string => rtrim($line, "\r"),
                explode("\n", $content),
            );
        }

        return str_split($content, $layout->lineLength);
    }
}
