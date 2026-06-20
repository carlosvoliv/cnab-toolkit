<?php

declare(strict_types=1);

namespace Cnab\Schema;

use Cnab\Exceptions\LayoutException;

/**
 * The shape of one record kind (header, detail, trailer, ...).
 *
 * A record is identified by the literal `code` found at the layout's type
 * position (e.g. "0" header, "1" detail, "9" trailer). Fields must tile the
 * whole line with no gaps and no overlaps.
 */
final readonly class RecordDefinition
{
    /** @var array<string, Field> */
    public array $fields;

    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $code,
        public int $lineLength,
        array $fields,
        public string $name = '',
    ) {
        $this->fields = self::indexAndValidate($fields, $lineLength, $code);
    }

    public function has(string $field): bool
    {
        return isset($this->fields[$field]);
    }

    public function field(string $name): Field
    {
        return $this->fields[$name]
            ?? throw new LayoutException(sprintf('Unknown field "%s" in record "%s".', $name, $this->code));
    }

    /**
     * @param  list<Field>  $fields
     * @return array<string, Field>
     */
    private static function indexAndValidate(array $fields, int $lineLength, string $code): array
    {
        if ($fields === []) {
            throw new LayoutException(sprintf('Record "%s" must declare at least one field.', $code));
        }

        usort($fields, static fn (Field $a, Field $b): int => $a->start <=> $b->start);

        $indexed = [];
        $cursor = 1;

        foreach ($fields as $field) {
            if (isset($indexed[$field->name])) {
                throw new LayoutException(sprintf('Duplicated field "%s" in record "%s".', $field->name, $code));
            }

            if ($field->start !== $cursor) {
                throw new LayoutException(sprintf(
                    'Record "%s": field "%s" starts at %d but column %d was expected (gap or overlap).',
                    $code,
                    $field->name,
                    $field->start,
                    $cursor,
                ));
            }

            $indexed[$field->name] = $field;
            $cursor = $field->end() + 1;
        }

        $covered = $cursor - 1;

        if ($covered !== $lineLength) {
            throw new LayoutException(sprintf(
                'Record "%s" covers %d columns but the layout line length is %d.',
                $code,
                $covered,
                $lineLength,
            ));
        }

        return $indexed;
    }
}
