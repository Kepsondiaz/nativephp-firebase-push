<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Bridge;

use Kepson\NativePhpFirebasePush\Contracts\BridgeDispatcher;
use Native\Mobile\PushNotifications;

/**
 * Concrete bridge that delegates to NativePHP Mobile's native push API. This is
 * the only class in the package that talks to `Native\Mobile` directly.
 */
final readonly class NativePushBridge implements BridgeDispatcher
{
    public function __construct(
        private PushNotifications $push,
    ) {}

    public function getToken(): ?string
    {
        return $this->push->getToken();
    }

    public function permissionStatus(): ?string
    {
        return $this->push->checkPermission();
    }

    public function requestPermission(): void
    {
        $this->push->enroll()->enroll();
    }
}
