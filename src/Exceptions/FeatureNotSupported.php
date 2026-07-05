<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Exceptions;

use RuntimeException;

/**
 * Thrown when a public API method is defined by the contract but not yet
 * available in the current milestone or on the current platform.
 */
final class FeatureNotSupported extends RuntimeException
{
    public static function method(string $method, string $reason): self
    {
        return new self("{$method}() is not supported: {$reason}");
    }
}
