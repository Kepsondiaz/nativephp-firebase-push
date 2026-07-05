<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Events;

/**
 * Dispatched when the FCM token is explicitly invalidated.
 * Dispatched exclusively by FirebasePushManager.
 */
final readonly class TokenRevoked {}
