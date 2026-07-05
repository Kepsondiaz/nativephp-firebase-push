<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Repositories;

use Kepson\NativePhpFirebasePush\Contracts\TokenRepository;
use Native\Mobile\SecureStorage;

/**
 * Persists the FCM token in NativePHP's native secure key-value store
 * (Android Keystore / iOS Keychain). The default driver.
 */
final readonly class NativeSessionTokenRepository implements TokenRepository
{
    private const KEY = 'firebase-push.token';

    public function __construct(
        private SecureStorage $storage,
    ) {}

    public function get(): ?string
    {
        return $this->storage->get(self::KEY);
    }

    public function store(string $token): void
    {
        $this->storage->set(self::KEY, $token);
    }

    public function forget(): void
    {
        $this->storage->delete(self::KEY);
    }

    public function has(): bool
    {
        return $this->get() !== null;
    }
}
