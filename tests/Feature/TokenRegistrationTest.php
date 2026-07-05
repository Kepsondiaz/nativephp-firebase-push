<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Events\Dispatcher;
use Kepson\NativePhpFirebasePush\Events\TokenReceived;
use Kepson\NativePhpFirebasePush\Exceptions\FeatureNotSupported;
use Kepson\NativePhpFirebasePush\FirebasePushManager;
use Kepson\NativePhpFirebasePush\Repositories\CacheTokenRepository;
use Kepson\NativePhpFirebasePush\Tests\Fakes\FakeBridgeDispatcher;

/**
 * @param  list<TokenReceived>  $captured
 */
function makeManager(
    FakeBridgeDispatcher $bridge,
    CacheTokenRepository $tokens,
    array &$captured,
    bool $dispatchEvents = true,
): FirebasePushManager {
    $events = new Dispatcher;
    $events->listen(TokenReceived::class, function (TokenReceived $event) use (&$captured) {
        $captured[] = $event;
    });

    return new FirebasePushManager($tokens, $bridge, $events, $dispatchEvents);
}

beforeEach(function () {
    $this->bridge = new FakeBridgeDispatcher;
    $this->tokens = new CacheTokenRepository(new Repository(new ArrayStore));
    $this->captured = [];
    $this->manager = makeManager($this->bridge, $this->tokens, $this->captured);
});

it('persists the token, dispatches TokenReceived and invokes callbacks on first delivery', function () {
    $callbackArgs = null;
    $this->manager->onTokenReceived(function (string $token, bool $refreshed) use (&$callbackArgs) {
        $callbackArgs = [$token, $refreshed];
    });

    $this->manager->handleNativeToken('token-1');

    expect($this->tokens->get())->toBe('token-1')
        ->and($this->captured)->toHaveCount(1)
        ->and($this->captured[0]->token)->toBe('token-1')
        ->and($this->captured[0]->refreshed)->toBeFalse()
        ->and($callbackArgs)->toBe(['token-1', false]);
});

it('marks the token as refreshed when it replaces a different stored token', function () {
    $this->manager->handleNativeToken('token-1');
    $this->manager->handleNativeToken('token-2');

    expect($this->tokens->get())->toBe('token-2')
        ->and($this->captured)->toHaveCount(2)
        ->and($this->captured[1]->token)->toBe('token-2')
        ->and($this->captured[1]->refreshed)->toBeTrue();
});

it('ignores a repeated delivery of the current token to avoid duplicate events', function () {
    $this->manager->handleNativeToken('token-1');
    $this->manager->handleNativeToken('token-1');

    expect($this->captured)->toHaveCount(1);
});

it('invokes callbacks but dispatches no event when event dispatch is disabled', function () {
    $captured = [];
    $manager = makeManager($this->bridge, $this->tokens, $captured, dispatchEvents: false);
    $called = false;
    $manager->onTokenReceived(function () use (&$called) {
        $called = true;
    });

    $manager->handleNativeToken('token-1');

    expect($captured)->toBeEmpty()
        ->and($called)->toBeTrue();
});

it('returns the stored token without querying the bridge', function () {
    $this->tokens->store('stored-token');
    $this->bridge->token = 'bridge-token';

    expect($this->manager->token())->toBe('stored-token');
});

it('lazily fetches the token from the bridge and persists it when none is stored', function () {
    $this->bridge->token = 'bridge-token';

    expect($this->manager->token())->toBe('bridge-token')
        ->and($this->tokens->get())->toBe('bridge-token');
});

it('returns null when neither storage nor the bridge has a token', function () {
    expect($this->manager->token())->toBeNull();
});

it('reports permission granted only when the native status is granted', function () {
    $this->bridge->permissionStatus = 'granted';
    expect($this->manager->isPermissionGranted())->toBeTrue();

    $this->bridge->permissionStatus = 'denied';
    expect($this->manager->isPermissionGranted())->toBeFalse();
});

it('delegates permission requests to the bridge', function () {
    $this->manager->requestPermission();

    expect($this->bridge->requestPermissionCalls)->toBe(1);
});

it('throws when revokeToken is called because no native revoke API exists', function () {
    $this->manager->revokeToken();
})->throws(FeatureNotSupported::class);

it('throws when registering an onTokenRevoked callback because revocation is unsupported', function () {
    $this->manager->onTokenRevoked(fn () => null);
})->throws(FeatureNotSupported::class);
