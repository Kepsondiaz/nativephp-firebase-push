<?php

declare(strict_types=1);

it('boots the package service provider and loads config', function () {
    expect(config('firebase-push.token_driver'))->toBe('session')
        ->and(config('firebase-push.dispatch_events'))->toBeTrue();
});
