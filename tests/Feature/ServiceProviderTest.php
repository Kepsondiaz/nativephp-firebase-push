<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kepson\NativePhpFirebasePush\Bridge\NativePushBridge;
use Kepson\NativePhpFirebasePush\Contracts\BridgeDispatcher;
use Kepson\NativePhpFirebasePush\Contracts\FirebasePushManager as FirebasePushManagerContract;
use Kepson\NativePhpFirebasePush\Contracts\TokenRepository;
use Kepson\NativePhpFirebasePush\Events\TokenReceived;
use Kepson\NativePhpFirebasePush\Facades\FirebasePush;
use Kepson\NativePhpFirebasePush\FirebasePushManager;
use Kepson\NativePhpFirebasePush\Repositories\CacheTokenRepository;
use Kepson\NativePhpFirebasePush\Repositories\NativeSessionTokenRepository;
use Native\Mobile\Events\PushNotification\TokenGenerated;

it('binds the manager as a singleton behind the contract', function () {
    $first = app(FirebasePushManagerContract::class);
    $second = app(FirebasePushManagerContract::class);

    expect($first)->toBeInstanceOf(FirebasePushManager::class)
        ->and($first)->toBe($second);
});

it('binds the native push bridge', function () {
    expect(app(BridgeDispatcher::class))->toBeInstanceOf(NativePushBridge::class);
});

it('resolves the native session token repository by default', function () {
    expect(app(TokenRepository::class))->toBeInstanceOf(NativeSessionTokenRepository::class);
});

it('resolves the cache token repository when configured', function () {
    config()->set('firebase-push.token_driver', 'cache');

    expect(app(TokenRepository::class))->toBeInstanceOf(CacheTokenRepository::class);
});

it('throws on an unsupported token driver', function () {
    config()->set('firebase-push.token_driver', 'redis');

    app(TokenRepository::class);
})->throws(InvalidArgumentException::class);

it('resolves the facade to the manager', function () {
    expect(FirebasePush::getFacadeRoot())->toBeInstanceOf(FirebasePushManager::class);
});

it('registers the firebase-push:token command', function () {
    $this->artisan('firebase-push:token')
        ->expectsOutputToContain('No FCM token is available yet.')
        ->assertSuccessful();
});

it('handles the native TokenGenerated event end to end', function () {
    config()->set('firebase-push.token_driver', 'cache');

    $captured = [];
    Event::listen(TokenReceived::class, function (TokenReceived $event) use (&$captured) {
        $captured[] = $event;
    });

    event(new TokenGenerated('native-token-1', 'enrollment-id'));

    expect(app(TokenRepository::class)->get())->toBe('native-token-1')
        ->and($captured)->toHaveCount(1)
        ->and($captured[0]->token)->toBe('native-token-1')
        ->and($captured[0]->refreshed)->toBeFalse();
});
