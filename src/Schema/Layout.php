<?php

declare(strict_types=1);

namespace Cnab\Schema;

use Cnab\Exceptions\LayoutException;

/**
 * A complete file layout: a fixed line length plus the record definitions
 * that may appear in it, keyed by their identifying code.
 *
 * The record-type code is read from a fixed window of every line
 * (`typeStart`/`typeLength`), which defaults to column 1, length 1 — the
 * convention used by the vast majority of CNAB layouts.
 */
final readonly class Layout
{
    /** @var array<string, RecordDefinition> */
    public array $records;

    /**
     * @param  list<RecordDefinition>  $records
     */
    public function __construct(
        public string $name,
        public int $lineLength,
        array $records,
        public string $headerCode = '0',
        public string $detailCode = '1',
        public string $trailerCode = '9',
        public int $typeStart = 1,
        public int $typeLength = 1,
    ) {
        if ($lineLength < 1) {
            throw new LayoutException('Layout line length must be >= 1.');
        }

        $indexed = [];

        foreach ($records as $record) {
            if ($record->lineLength !== $lineLength) {
                throw new LayoutException(sprintf(
                    'Record "%s" declares line length %d but layout "%s" uses %d.',
                    $record->code,
                    $record->lineLength,
                    $name,
                    $lineLength,
                ));
            }

            if (isset($indexed[$record->code])) {
                throw new LayoutException(sprintf('Duplicated record code "%s" in layout "%s".', $record->code, $name));
            }

            $indexed[$record->code] = $record;
        }

        if ($indexed === []) {
            throw new LayoutException(sprintf('Layout "%s" must declare at least one record.', $name));
        }

        $this->records = $indexed;
    }

    public function hasRecord(string $code): bool
    {
        return isset($this->records[$code]);
    }

    public function record(string $code): RecordDefinition
    {
        return $this->records[$code]
            ?? throw new LayoutException(sprintf('Layout "%s" has no record with code "%s".', $this->name, $code));
    }

    /** Extract the record-type code from a raw line. */
    public function codeOf(string $line): string
    {
        return substr($line, $this->typeStart - 1, $this->typeLength);
    }
}
