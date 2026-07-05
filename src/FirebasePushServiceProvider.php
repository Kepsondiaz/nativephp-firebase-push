<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Kepson\NativePhpFirebasePush\Bridge\NativePushBridge;
use Kepson\NativePhpFirebasePush\Commands\TokenCommand;
use Kepson\NativePhpFirebasePush\Contracts\BridgeDispatcher;
use Kepson\NativePhpFirebasePush\Contracts\FirebasePushManager as FirebasePushManagerContract;
use Kepson\NativePhpFirebasePush\Contracts\TokenRepository;
use Kepson\NativePhpFirebasePush\Repositories\CacheTokenRepository;
use Kepson\NativePhpFirebasePush\Repositories\NativeSessionTokenRepository;
use Native\Mobile\Events\PushNotification\TokenGenerated;

final class FirebasePushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/firebase-push.php', 'firebase-push');

        $this->app->bind(BridgeDispatcher::class, NativePushBridge::class);

        $this->app->bind(TokenRepository::class, $this->tokenRepositoryResolver());

        $this->app->singleton(FirebasePushManager::class, function (Application $app): FirebasePushManager {
            return new FirebasePushManager(
                $app->make(TokenRepository::class),
                $app->make(BridgeDispatcher::class),
                $app->make(Dispatcher::class),
                (bool) config('firebase-push.dispatch_events', true),
            );
        });

        $this->app->alias(FirebasePushManager::class, FirebasePushManagerContract::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/firebase-push.php' => config_path('firebase-push.php'),
            ], 'firebase-push-config');

            $this->commands([
                TokenCommand::class,
            ]);
        }

        $this->app->make(Dispatcher::class)->listen(
            TokenGenerated::class,
            function (TokenGenerated $event): void {
                $this->app->make(FirebasePushManager::class)->handleNativeToken($event->token);
            },
        );
    }

    /**
     * Resolve the configured token repository implementation.
     *
     * @return \Closure(Application): TokenRepository
     */
    private function tokenRepositoryResolver(): \Closure
    {
        return function (Application $app): TokenRepository {
            $driver = config('firebase-push.token_driver', 'session');

            return match ($driver) {
                'session' => $app->make(NativeSessionTokenRepository::class),
                'cache' => new CacheTokenRepository($app->make('cache')->store()),
                default => throw new InvalidArgumentException(
                    "Unsupported firebase-push token_driver [{$driver}]. Use 'session' or 'cache'.",
                ),
            };
        };
    }
}
