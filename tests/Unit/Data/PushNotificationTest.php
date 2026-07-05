<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Kepson\NativePhpFirebasePush\Data\PushNotification;

it('builds a full notification from a bridge payload', function () {
    $notification = PushNotification::fromBridgePayload([
        'id' => 'abc-123',
        'title' => 'Hello',
        'body' => 'World',
        'imageUrl' => 'https://example.test/pic.png',
        'data' => ['orderId' => '42', 'kind' => 'shipment'],
        'sentAt' => '2026-07-04T10:00:00+00:00',
        'receivedAt' => '2026-07-04T10:00:05+00:00',
        'channel' => 'orders',
        'collapseKey' => 'order-42',
        'tapped' => true,
        'foreground' => false,
    ]);

    expect($notification->id)->toBe('abc-123')
        ->and($notification->title)->toBe('Hello')
        ->and($notification->body)->toBe('World')
        ->and($notification->imageUrl)->toBe('https://example.test/pic.png')
        ->and($notification->data)->toBe(['orderId' => '42', 'kind' => 'shipment'])
        ->and($notification->sentAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($notification->sentAt?->toIso8601String())->toBe('2026-07-04T10:00:00+00:00')
        ->and($notification->receivedAt->toIso8601String())->toBe('2026-07-04T10:00:05+00:00')
        ->and($notification->channel)->toBe('orders')
        ->and($notification->collapseKey)->toBe('order-42')
        ->and($notification->tapped)->toBeTrue()
        ->and($notification->foreground)->toBeFalse();
});

it('defaults optional fields when absent from the payload', function () {
    $notification = PushNotification::fromBridgePayload(['id' => 'only-id']);

    expect($notification->id)->toBe('only-id')
        ->and($notification->title)->toBeNull()
        ->and($notification->body)->toBeNull()
        ->and($notification->imageUrl)->toBeNull()
        ->and($notification->data)->toBe([])
        ->and($notification->sentAt)->toBeNull()
        ->and($notification->channel)->toBeNull()
        ->and($notification->collapseKey)->toBeNull()
        ->and($notification->tapped)->toBeFalse()
        ->and($notification->foreground)->toBeFalse();
});

it('records receivedAt as now when the payload omits it', function () {
    $before = CarbonImmutable::now('UTC')->subSecond();

    $notification = PushNotification::fromBridgePayload(['id' => 'x']);

    expect($notification->receivedAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($notification->receivedAt->greaterThanOrEqualTo($before))->toBeTrue();
});

it('coerces all data values to strings', function () {
    $notification = PushNotification::fromBridgePayload([
        'id' => 'x',
        'data' => ['count' => 7, 'flag' => true, 'ratio' => 1.5],
    ]);

    expect($notification->data)->toBe(['count' => '7', 'flag' => '1', 'ratio' => '1.5']);
});

it('round-trips through toArray with iso 8601 timestamps', function () {
    $payload = [
        'id' => 'abc-123',
        'title' => 'Hello',
        'body' => null,
        'imageUrl' => null,
        'data' => ['k' => 'v'],
        'sentAt' => '2026-07-04T10:00:00+00:00',
        'receivedAt' => '2026-07-04T10:00:05+00:00',
        'channel' => null,
        'collapseKey' => null,
        'tapped' => false,
        'foreground' => true,
    ];

    expect(PushNotification::fromBridgePayload($payload)->toArray())->toBe([
        'id' => 'abc-123',
        'title' => 'Hello',
        'body' => null,
        'imageUrl' => null,
        'data' => ['k' => 'v'],
        'sentAt' => '2026-07-04T10:00:00+00:00',
        'receivedAt' => '2026-07-04T10:00:05+00:00',
        'channel' => null,
        'collapseKey' => null,
        'tapped' => false,
        'foreground' => true,
    ]);
});
