<?php

declare(strict_types=1);

use Kepson\NativePhpFirebasePush\Repositories\NativeSessionTokenRepository;
use Native\Mobile\SecureStorage;

const TOKEN_KEY = 'firebase-push.token';

it('reads the token from secure storage', function () {
    $storage = Mockery::mock(SecureStorage::class);
    $storage->shouldReceive('get')->once()->with(TOKEN_KEY)->andReturn('fcm-token-abc');

    expect((new NativeSessionTokenRepository($storage))->get())->toBe('fcm-token-abc');
});

it('returns null and reports absence when secure storage is empty', function () {
    $storage = Mockery::mock(SecureStorage::class);
    $storage->shouldReceive('get')->with(TOKEN_KEY)->andReturnNull();

    $repository = new NativeSessionTokenRepository($storage);

    expect($repository->get())->toBeNull()
        ->and($repository->has())->toBeFalse();
});

it('writes the token to secure storage', function () {
    $storage = Mockery::mock(SecureStorage::class);
    $storage->shouldReceive('set')->once()->with(TOKEN_KEY, 'fcm-token-abc')->andReturnTrue();

    (new NativeSessionTokenRepository($storage))->store('fcm-token-abc');
});

it('deletes the token from secure storage', function () {
    $storage = Mockery::mock(SecureStorage::class);
    $storage->shouldReceive('delete')->once()->with(TOKEN_KEY)->andReturnTrue();

    (new NativeSessionTokenRepository($storage))->forget();
});

it('reports presence when a token exists', function () {
    $storage = Mockery::mock(SecureStorage::class);
    $storage->shouldReceive('get')->with(TOKEN_KEY)->andReturn('fcm-token-abc');

    expect((new NativeSessionTokenRepository($storage))->has())->toBeTrue();
});
