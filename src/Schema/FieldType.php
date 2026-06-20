<?php

declare(strict_types=1);

namespace Cnab\Schema;

/**
 * The two primitive content types of a CNAB field, mirroring the
 * "Tp(Dig)" column found in bank layout manuals:
 *
 *  - Numeric      -> picture 9(n): right-aligned, left zero-padded.
 *  - Alphanumeric -> picture X(n): left-aligned, right space-padded.
 */
enum FieldType: string
{
    case Numeric = 'numeric';
    case Alphanumeric = 'alphanumeric';

    public function isNumeric(): bool
    {
        return $this === self::Numeric;
    }
}
