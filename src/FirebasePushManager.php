<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush;

use Illuminate\Contracts\Events\Dispatcher;
use Kepson\NativePhpFirebasePush\Contracts\BridgeDispatcher;
use Kepson\NativePhpFirebasePush\Contracts\FirebasePushManager as FirebasePushManagerContract;
use Kepson\NativePhpFirebasePush\Contracts\TokenRepository;
use Kepson\NativePhpFirebasePush\Events\TokenReceived;
use Kepson\NativePhpFirebasePush\Exceptions\FeatureNotSupported;

/**
 * Single concrete implementation of the package's public API and the only class
 * permitted to dispatch the package's Laravel events.
 *
 * v0.1 scope is token registration: token retrieval/persistence and reacting to
 * the native TokenGenerated event. Notification and revocation behaviour arrive
 * in later milestones and currently fail loudly rather than pretend to work.
 */
final class FirebasePushManager implements FirebasePushManagerContract
{
    /** @var list<callable(string, bool): void> */
    private array $onTokenReceived = [];

    public function __construct(
        private readonly TokenRepository $tokens,
        private readonly BridgeDispatcher $bridge,
        private readonly Dispatcher $events,
        private readonly bool $dispatchEvents,
    ) {}

    public function token(): ?string
    {
        $stored = $this->tokens->get();

        if ($stored !== null) {
            return $stored;
        }

        $token = $this->bridge->getToken();

        if ($token !== null) {
            $this->tokens->store($token);
        }

        return $token;
    }

    public function requestPermission(): void
    {
        $this->bridge->requestPermission();
    }

    public function isPermissionGranted(): bool
    {
        return $this->bridge->permissionStatus() === 'granted';
    }

    public function revokeToken(): void
    {
        throw FeatureNotSupported::method(
            'revokeToken',
            'NativePHP Mobile exposes no token deletion API yet',
        );
    }

    public function onTokenReceived(callable $callback): void
    {
        $this->onTokenReceived[] = $callback;
    }

    public function onTokenRevoked(callable $callback): void
    {
        throw FeatureNotSupported::method('onTokenRevoked', 'token revocation arrives in a later milestone');
    }

    public function onNotificationReceived(callable $callback): void
    {
        throw FeatureNotSupported::method('onNotificationReceived', 'foreground notification delivery arrives in a later milestone');
    }

    public function onNotificationTapped(callable $callback): void
    {
        throw FeatureNotSupported::method('onNotificationTapped', 'notification tap handling arrives in a later milestone');
    }

    public function onPermissionGranted(callable $callback): void
    {
        throw FeatureNotSupported::method('onPermissionGranted', 'permission result callbacks arrive in a later milestone');
    }

    public function onPermissionDenied(callable $callback): void
    {
        throw FeatureNotSupported::method('onPermissionDenied', 'permission result callbacks arrive in a later milestone');
    }

    /**
     * Handle a token delivered by the native TokenGenerated event. Persists the
     * token, fires TokenReceived (when event dispatch is enabled) and invokes
     * registered onTokenReceived callbacks. Deliveries that match the currently
     * stored token are ignored to prevent duplicate events on cold start.
     *
     * Called by the service provider's TokenGenerated listener — not part of
     * the consumer-facing contract.
     */
    public function handleNativeToken(string $token): void
    {
        $previous = $this->tokens->get();

        if ($previous === $token) {
            return;
        }

        $refreshed = $previous !== null;

        $this->tokens->store($token);

        if ($this->dispatchEvents) {
            $this->events->dispatch(new TokenReceived($token, $refreshed));
        }

        foreach ($this->onTokenReceived as $callback) {
            $callback($token, $refreshed);
        }
    }
}
