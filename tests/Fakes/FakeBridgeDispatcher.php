<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Tests\Fakes;

use Kepson\NativePhpFirebasePush\Contracts\BridgeDispatcher;

/**
 * In-memory BridgeDispatcher for feature tests. Lets a test seed the native
 * token and permission status and assert that enrollment was requested — no
 * real device or NativePHP runtime required.
 */
final class FakeBridgeDispatcher implements BridgeDispatcher
{
    public ?string $token = null;

    public ?string $permissionStatus = 'not_determined';

    public int $requestPermissionCalls = 0;

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function permissionStatus(): ?string
    {
        return $this->permissionStatus;
    }

    public function requestPermission(): void
    {
        $this->requestPermissionCalls++;
    }
}
