<?php

namespace App\Exceptions;

use App\Data\FilterDefinition;
use RuntimeException;

/**
 * Thrown by {@see FilterDefinition} when untrusted input fails to
 * validate. The message is the single, positionally-worded first error
 * (e.g. `Unknown field "age" in group 2, condition 1.`), ready to surface in
 * an import dialog or a fallback notice.
 */
final class InvalidFilterDefinition extends RuntimeException {}
