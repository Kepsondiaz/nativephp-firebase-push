<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Contracts;

/**
 * Persistence contract for the current device FCM registration token.
 *
 * Implementations decide where the token lives (NativePHP native session store,
 * Laravel cache, etc.). The active implementation is selected by the
 * `token_driver` configuration key and resolved in the service provider.
 */
interface TokenRepository
{
    /**
     * Return the currently stored FCM token, or null if none has been stored.
     */
    public function get(): ?string;

    /**
     * Persist the given FCM token, replacing any previously stored value.
     *
     * @param  string  $token  The FCM registration token to store.
     */
    public function store(string $token): void;

    /**
     * Remove any stored FCM token.
     */
    public function forget(): void;

    /**
     * Return whether a token is currently stored.
     */
    public function has(): bool;
}
