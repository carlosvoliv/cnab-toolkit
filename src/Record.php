<?php

declare(strict_types=1);

namespace Cnab;

use Cnab\Exceptions\CnabException;
use Cnab\Schema\RecordDefinition;

/**
 * A parsed (or to-be-written) record: a record definition bound to a set of
 * field values keyed by field name.
 */
final class Record
{
    /**
     * @param  array<string, string>  $values
     */
    public function __construct(
        public readonly RecordDefinition $definition,
        private array $values = [],
    ) {}

    public function code(): string
    {
        return $this->definition->code;
    }

    public function get(string $field): string
    {
        if (! array_key_exists($field, $this->values)) {
            throw new CnabException(sprintf('Field "%s" is not set on record "%s".', $field, $this->code()));
        }

        return $this->values[$field];
    }

    public function tryGet(string $field, ?string $default = null): ?string
    {
        return $this->values[$field] ?? $default;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    public function set(string $field, string|int|float|null $value): self
    {
        $this->definition->field($field); // validates the name exists
        $this->values[$field] = $value === null ? '' : (string) $value;

        return $this;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->values;
    }
}
