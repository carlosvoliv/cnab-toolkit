<?php

declare(strict_types=1);

namespace Cnab\Exceptions;

/**
 * Raised when a layout/record definition is malformed (overlaps, gaps,
 * wrong total width, duplicated field names, ...).
 */
final class LayoutException extends CnabException {}
