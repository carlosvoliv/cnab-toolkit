<?php

declare(strict_types=1);

namespace Cnab\Formatting;

use Cnab\Exceptions\EncodingException;
use Cnab\Schema\Field;
use Cnab\Schema\FieldType;
use Cnab\Support\Decimal;

/**
 * Encodes PHP values into the fixed-width slice of a field and decodes them
 * back, applying the alignment/padding rules from the CNAB general provisions:
 *
 *  - Numeric:      right-aligned, left zero-padded; decimals are implied.
 *  - Alphanumeric: left-aligned, right space-padded.
 *
 * Decoded numeric values are returned as strings so that fields wider than a
 * 64-bit integer (e.g. 9(20)) round-trip without loss.
 */
final class FieldCodec
{
    /** Encode a value into exactly $field->length characters. */
    public function encode(Field $field, string|int|float|null $value): string
    {
        $value ??= $field->default;

        return $field->type === FieldType::Numeric
            ? $this->encodeNumeric($field, $value)
            : $this->encodeAlpha($field, $value);
    }

    /** Decode the raw slice of a field into a normalized PHP value. */
    public function decode(Field $field, string $raw): string
    {
        if ($field->type === FieldType::Numeric) {
            // Monetary fields are normalized to a decimal string; plain numeric
            // fields keep their exact digits so leading zeros in dates,
            // sequences and codes (e.g. "010826", "000001") survive the round
            // trip. Consumers can cast to int when they want a number.
            return $field->decimals > 0
                ? Decimal::fromScaledInt($raw === '' ? '0' : $raw, $field->decimals)
                : $raw;
        }

        return rtrim($raw, ' ');
    }

    private function encodeNumeric(Field $field, string|int|float|null $value): string
    {
        $scaled = $value === null || $value === ''
            ? '0'
            : Decimal::toScaledInt($value, $field->decimals);

        if (str_starts_with($scaled, '-')) {
            throw new EncodingException(sprintf('Field "%s" cannot store negative values.', $field->name));
        }

        if (strlen($scaled) > $field->length) {
            throw new EncodingException(sprintf(
                'Value "%s" does not fit numeric field "%s" of length %d (needs %d digits).',
                $value,
                $field->name,
                $field->length,
                strlen($scaled),
            ));
        }

        return str_pad($scaled, $field->length, '0', STR_PAD_LEFT);
    }

    private function encodeAlpha(Field $field, string|int|float|null $value): string
    {
        $text = (string) ($value ?? '');

        if (strlen($text) > $field->length) {
            throw new EncodingException(sprintf(
                'Value "%s" does not fit alphanumeric field "%s" of length %d.',
                $text,
                $field->name,
                $field->length,
            ));
        }

        return str_pad($text, $field->length, ' ', STR_PAD_RIGHT);
    }
}
