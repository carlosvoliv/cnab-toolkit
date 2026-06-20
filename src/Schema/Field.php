<?php

declare(strict_types=1);

namespace Cnab\Schema;

use Cnab\Exceptions\LayoutException;

/**
 * A single fixed-width field within a record.
 *
 * Positions are 1-based and inclusive, exactly as bank manuals describe them
 * ("Início" / "Fim"): a field starting at 545 with length 6 occupies columns
 * 545..550.
 */
final readonly class Field
{
    public function __construct(
        public string $name,
        public int $start,
        public int $length,
        public FieldType $type = FieldType::Alphanumeric,
        public int $decimals = 0,
        public string $description = '',
        public string|int|float|null $default = null,
    ) {
        if ($name === '') {
            throw new LayoutException('Field name cannot be empty.');
        }

        if ($start < 1) {
            throw new LayoutException(sprintf('Field "%s" must start at column >= 1, got %d.', $name, $start));
        }

        if ($length < 1) {
            throw new LayoutException(sprintf('Field "%s" must have length >= 1, got %d.', $name, $length));
        }

        if ($decimals < 0) {
            throw new LayoutException(sprintf('Field "%s" cannot have negative decimals.', $name));
        }

        if ($decimals > 0 && $type !== FieldType::Numeric) {
            throw new LayoutException(sprintf('Field "%s" uses decimals but is not numeric.', $name));
        }
    }

    /** Inclusive 1-based end column. */
    public function end(): int
    {
        return $this->start + $this->length - 1;
    }

    /** 0-based offset, ready for substr(). */
    public function offset(): int
    {
        return $this->start - 1;
    }

    /** Convenience factory for a numeric field. */
    public static function numeric(string $name, int $start, int $length, int $decimals = 0, string $description = ''): self
    {
        return new self($name, $start, $length, FieldType::Numeric, $decimals, $description);
    }

    /** Convenience factory for an alphanumeric field. */
    public static function alpha(string $name, int $start, int $length, string $description = ''): self
    {
        return new self($name, $start, $length, FieldType::Alphanumeric, 0, $description);
    }

    /** Convenience factory for a reserved/blank filler region. */
    public static function filler(int $start, int $length, FieldType $type = FieldType::Alphanumeric): self
    {
        return new self(sprintf('filler_%d', $start), $start, $length, $type, description: 'Reserved');
    }
}
