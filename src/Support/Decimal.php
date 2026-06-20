<?php

declare(strict_types=1);

namespace Cnab\Support;

use Cnab\Exceptions\EncodingException;

/**
 * String-based fixed-point scaling.
 *
 * CNAB stores monetary values as integers with an implied number of decimal
 * places (e.g. "000000000247056" with 2 decimals means 2470.56). We work on
 * digit strings instead of floats to keep precision on large fields such as
 * 9(13)v2 — well beyond what a binary float can hold safely.
 */
final class Decimal
{
    /**
     * Turn a human decimal ("2470.56", "2470,56", 2470.56, 2470) into the
     * scaled integer string the file stores ("247056" for scale 2).
     */
    public static function toScaledInt(string|int|float $value, int $scale): string
    {
        $raw = is_float($value)
            ? rtrim(rtrim(sprintf('%.'.max($scale, 8).'F', $value), '0'), '.')
            : (string) $value;

        $raw = str_replace(',', '.', trim($raw));

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '+-');

        if ($raw === '' || ! preg_match('/^\d*(\.\d*)?$/', $raw)) {
            throw new EncodingException(sprintf('Value "%s" is not a valid decimal.', $value));
        }

        [$intPart, $fracPart] = array_pad(explode('.', $raw, 2), 2, '');

        $fracPart = substr(str_pad($fracPart, $scale, '0'), 0, $scale);
        $digits = ltrim($intPart.$fracPart, '0');
        $digits = $digits === '' ? '0' : $digits;

        return ($negative && $digits !== '0' ? '-' : '').$digits;
    }

    /**
     * Inverse of {@see toScaledInt()}: a scaled integer string back into a
     * human decimal string ("247056" + scale 2 -> "2470.56").
     */
    public static function fromScaledInt(string $scaledInt, int $scale): string
    {
        $negative = str_starts_with($scaledInt, '-');
        $digits = ltrim(ltrim($scaledInt, '+-'), '0');
        $digits = $digits === '' ? '0' : $digits;

        if ($scale === 0) {
            return ($negative && $digits !== '0' ? '-' : '').$digits;
        }

        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $intPart = substr($digits, 0, -$scale);
        $fracPart = substr($digits, -$scale);

        $result = $intPart.'.'.$fracPart;

        return ($negative && (int) $digits !== 0 ? '-' : '').$result;
    }
}
