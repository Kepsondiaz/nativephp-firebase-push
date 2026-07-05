<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Events;

/**
 * Dispatched when a new FCM registration token is acquired or refreshed.
 * Dispatched exclusively by FirebasePushManager.
 */
final readonly class TokenReceived
{
    public function __construct(
        public string $token,
        public bool $refreshed,
    ) {}
}
