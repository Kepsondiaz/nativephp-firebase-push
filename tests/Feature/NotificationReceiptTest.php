<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Kepson\NativePhpFirebasePush\Bridge\Events\NativeNotificationReceived;
use Kepson\NativePhpFirebasePush\Bridge\Events\NativeNotificationTapped;
use Kepson\NativePhpFirebasePush\Events\NotificationReceived;
use Kepson\NativePhpFirebasePush\Events\NotificationTapped;
use Kepson\NativePhpFirebasePush\FirebasePushManager;
use Kepson\NativePhpFirebasePush\Repositories\CacheTokenRepository;
use Kepson\NativePhpFirebasePush\Tests\Fakes\FakeBridgeDispatcher;

/**
 * @param  list<object>  $captured
 */
function notifManager(array &$captured, bool $dispatchEvents = true): FirebasePushManager
{
    $events = new Dispatcher;
    $events->listen(NotificationReceived::class, function (NotificationReceived $e) use (&$captured) {
        $captured[] = $e;
    });
    $events->listen(NotificationTapped::class, function (NotificationTapped $e) use (&$captured) {
        $captured[] = $e;
    });

    return new FirebasePushManager(
        new CacheTokenRepository(new Repository(new ArrayStore)),
        new FakeBridgeDispatcher,
        $events,
        $dispatchEvents,
    );
}

it('dispatches NotificationReceived and marks it foreground', function () {
    $captured = [];
    $manager = notifManager($captured);
    $seen = null;
    $manager->onNotificationReceived(function ($notification) use (&$seen) {
        $seen = $notification;
    });

    $manager->handleNativeNotificationReceived(['id' => 'n1', 'title' => 'Hi', 'foreground' => false]);

    expect($captured)->toHaveCount(1)
        ->and($captured[0])->toBeInstanceOf(NotificationReceived::class)
        ->and($captured[0]->notification->id)->toBe('n1')
        ->and($captured[0]->notification->foreground)->toBeTrue()
        ->and($captured[0]->notification->tapped)->toBeFalse()
        ->and($seen?->id)->toBe('n1');
});

it('dispatches NotificationTapped, marks it tapped and resolves the deep link', function () {
    $captured = [];
    $manager = notifManager($captured);

    $manager->handleNativeNotificationTapped(['id' => 'n2', 'url' => '/orders/42']);

    expect($captured)->toHaveCount(1)
        ->and($captured[0])->toBeInstanceOf(NotificationTapped::class)
        ->and($captured[0]->notification->id)->toBe('n2')
        ->and($captured[0]->notification->tapped)->toBeTrue()
        ->and($captured[0]->notification->link)->toBe('/orders/42');
});

it('invokes notification callbacks but dispatches no event when event dispatch is disabled', function () {
    $captured = [];
    $manager = notifManager($captured, dispatchEvents: false);
    $called = false;
    $manager->onNotificationReceived(function () use (&$called) {
        $called = true;
    });

    $manager->handleNativeNotificationReceived(['id' => 'n3']);

    expect($captured)->toBeEmpty()
        ->and($called)->toBeTrue();
});

it('forwards the native received event through the provider to the public event', function () {
    $captured = [];
    Event::listen(NotificationReceived::class, function (NotificationReceived $e) use (&$captured) {
        $captured[] = $e;
    });

    event(new NativeNotificationReceived(['id' => 'wired-1', 'title' => 'Hello']));

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->notification->id)->toBe('wired-1')
        ->and($captured[0]->notification->foreground)->toBeTrue();
});

it('forwards the native tapped event through the provider to the public event', function () {
    $captured = [];
    Event::listen(NotificationTapped::class, function (NotificationTapped $e) use (&$captured) {
        $captured[] = $e;
    });

    event(new NativeNotificationTapped(['id' => 'wired-2', 'link' => '/deep']));

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->notification->id)->toBe('wired-2')
        ->and($captured[0]->notification->tapped)->toBeTrue()
        ->and($captured[0]->notification->link)->toBe('/deep');
});
