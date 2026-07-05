<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Events;

use Kepson\NativePhpFirebasePush\Data\PushNotification;

/**
 * Dispatched when the user taps a notification to open or foreground the app.
 * Dispatched exclusively by FirebasePushManager.
 */
final readonly class NotificationTapped
{
    public function __construct(
        public PushNotification $notification,
    ) {}
}
