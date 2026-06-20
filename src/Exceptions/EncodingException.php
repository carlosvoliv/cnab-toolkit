<?php

declare(strict_types=1);

namespace Cnab\Exceptions;

/**
 * Raised when a value cannot be encoded into a field
 * (does not fit, wrong type, non-numeric content, ...).
 */
final class EncodingException extends CnabException {}
