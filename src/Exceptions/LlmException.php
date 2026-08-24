<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Exceptions;

use RuntimeException;

/**
 * Base for every failure raised by this package, so callers can catch the whole
 * family with one clause.
 */
class LlmException extends RuntimeException
{
}
