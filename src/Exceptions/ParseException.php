<?php

declare(strict_types=1);

namespace Cnab\Exceptions;

/**
 * Raised when an input file does not conform to its layout
 * (wrong line length, unknown record type, ...).
 */
final class ParseException extends CnabException {}
