<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Kepson\NativePhpFirebasePush\Repositories\CacheTokenRepository;

beforeEach(function () {
    $this->repository = new CacheTokenRepository(new Repository(new ArrayStore));
});

it('returns null when no token is stored', function () {
    expect($this->repository->get())->toBeNull()
        ->and($this->repository->has())->toBeFalse();
});

it('stores and retrieves a token', function () {
    $this->repository->store('fcm-token-abc');

    expect($this->repository->get())->toBe('fcm-token-abc')
        ->and($this->repository->has())->toBeTrue();
});

it('overwrites a previously stored token', function () {
    $this->repository->store('first');
    $this->repository->store('second');

    expect($this->repository->get())->toBe('second');
});

it('forgets a stored token', function () {
    $this->repository->store('fcm-token-abc');
    $this->repository->forget();

    expect($this->repository->get())->toBeNull()
        ->and($this->repository->has())->toBeFalse();
});
