<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Kepson\NativePhpFirebasePush\Bridge\Events\NativePermissionResult;
use Kepson\NativePhpFirebasePush\Events\PermissionDenied;
use Kepson\NativePhpFirebasePush\Events\PermissionGranted;
use Kepson\NativePhpFirebasePush\FirebasePushManager;
use Kepson\NativePhpFirebasePush\Repositories\CacheTokenRepository;
use Kepson\NativePhpFirebasePush\Tests\Fakes\FakeBridgeDispatcher;

/**
 * @param  list<object>  $captured
 */
function permissionManager(array &$captured, bool $dispatchEvents = true): FirebasePushManager
{
    $events = new Dispatcher;
    $events->listen(PermissionGranted::class, function (PermissionGranted $e) use (&$captured) {
        $captured[] = $e;
    });
    $events->listen(PermissionDenied::class, function (PermissionDenied $e) use (&$captured) {
        $captured[] = $e;
    });

    return new FirebasePushManager(
        new CacheTokenRepository(new Repository(new ArrayStore)),
        new FakeBridgeDispatcher,
        $events,
        $dispatchEvents,
    );
}

it('dispatches PermissionGranted and invokes callbacks for a granted result', function (string $status) {
    $captured = [];
    $manager = permissionManager($captured);
    $called = false;
    $manager->onPermissionGranted(function () use (&$called) {
        $called = true;
    });

    $manager->handleNativePermissionResult($status);

    expect($captured)->toHaveCount(1)
        ->and($captured[0])->toBeInstanceOf(PermissionGranted::class)
        ->and($called)->toBeTrue();
})->with(['granted', 'provisional', 'ephemeral']);

it('dispatches PermissionDenied and invokes callbacks for a denied result', function () {
    $captured = [];
    $manager = permissionManager($captured);
    $called = false;
    $manager->onPermissionDenied(function () use (&$called) {
        $called = true;
    });

    $manager->handleNativePermissionResult('denied');

    expect($captured)->toHaveCount(1)
        ->and($captured[0])->toBeInstanceOf(PermissionDenied::class)
        ->and($called)->toBeTrue();
});

it('dispatches nothing for an undecided permission status', function () {
    $captured = [];
    $manager = permissionManager($captured);

    $manager->handleNativePermissionResult('not_determined');

    expect($captured)->toBeEmpty();
});

it('forwards the native permission result through the provider to the public event', function () {
    $captured = [];
    Event::listen(PermissionGranted::class, function (PermissionGranted $e) use (&$captured) {
        $captured[] = $e;
    });

    event(new NativePermissionResult('granted'));

    expect($captured)->toHaveCount(1);
});
