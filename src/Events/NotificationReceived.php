<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Events;

use Kepson\NativePhpFirebasePush\Data\PushNotification;

/**
 * Dispatched when a push notification arrives while the app is in the foreground.
 * Dispatched exclusively by FirebasePushManager.
 */
final readonly class NotificationReceived
{
    public function __construct(
        public PushNotification $notification,
    ) {}
}
