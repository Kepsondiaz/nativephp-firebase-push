<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Repositories;

use Illuminate\Contracts\Cache\Repository as Cache;
use Kepson\NativePhpFirebasePush\Contracts\TokenRepository;

/**
 * Persists the FCM token in the configured Laravel cache store. Suitable for
 * web-first apps that also drive a mobile client.
 */
final readonly class CacheTokenRepository implements TokenRepository
{
    private const KEY = 'firebase-push.token';

    public function __construct(
        private Cache $cache,
    ) {}

    public function get(): ?string
    {
        $token = $this->cache->get(self::KEY);

        return is_string($token) ? $token : null;
    }

    public function store(string $token): void
    {
        $this->cache->forever(self::KEY, $token);
    }

    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }

    public function has(): bool
    {
        return $this->get() !== null;
    }
}
