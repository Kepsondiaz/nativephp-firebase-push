<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Facades;

use Illuminate\Support\Facades\Facade;
use Kepson\NativePhpFirebasePush\Contracts\FirebasePushManager;

/**
 * @method static string|null token()
 * @method static void requestPermission()
 * @method static bool isPermissionGranted()
 * @method static void revokeToken()
 * @method static void onTokenReceived(callable $callback)
 * @method static void onTokenRevoked(callable $callback)
 * @method static void onNotificationReceived(callable $callback)
 * @method static void onNotificationTapped(callable $callback)
 * @method static void onPermissionGranted(callable $callback)
 * @method static void onPermissionDenied(callable $callback)
 *
 * @see FirebasePushManager
 */
final class FirebasePush extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FirebasePushManager::class;
    }
}
