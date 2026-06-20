<?php

declare(strict_types=1);

namespace Cnab;

use Cnab\Exceptions\CnabException;
use Cnab\Schema\Layout;

/**
 * An ordered collection of records belonging to one layout, with convenient
 * accessors for the usual header / details / trailer structure.
 */
final class CnabFile
{
    /**
     * @param  list<Record>  $records
     */
    public function __construct(
        public readonly Layout $layout,
        private array $records = [],
    ) {}

    public function add(Record $record): self
    {
        $this->records[] = $record;

        return $this;
    }

    /** @return list<Record> */
    public function records(): array
    {
        return $this->records;
    }

    public function header(): Record
    {
        foreach ($this->records as $record) {
            if ($record->code() === $this->layout->headerCode) {
                return $record;
            }
        }

        throw new CnabException('File has no header record.');
    }

    public function trailer(): Record
    {
        foreach (array_reverse($this->records) as $record) {
            if ($record->code() === $this->layout->trailerCode) {
                return $record;
            }
        }

        throw new CnabException('File has no trailer record.');
    }

    /** @return list<Record> */
    public function details(): array
    {
        return array_values(array_filter(
            $this->records,
            fn (Record $record): bool => $record->code() === $this->layout->detailCode,
        ));
    }

    public function count(): int
    {
        return count($this->records);
    }
}
