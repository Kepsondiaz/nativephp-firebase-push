<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kepson\NativePhpFirebasePush\Events\NotificationReceived;

it('simulates a notification that reaches notification handlers', function () {
    $captured = [];
    Event::listen(NotificationReceived::class, function (NotificationReceived $e) use (&$captured) {
        $captured[] = $e;
    });

    $this->artisan('firebase-push:test', [
        'token' => 'device-token',
        '--title' => 'Ping',
        '--url' => '/inbox',
    ])->assertSuccessful();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->notification->title)->toBe('Ping')
        ->and($captured[0]->notification->link)->toBe('/inbox')
        ->and($captured[0]->notification->foreground)->toBeTrue();
});
