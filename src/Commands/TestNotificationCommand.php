<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Kepson\NativePhpFirebasePush\Bridge\Events\NativeNotificationReceived;

/**
 * Simulates an inbound FCM notification locally by dispatching the native
 * received event, so a developer can exercise their onNotificationReceived
 * handlers and listeners without sending a real FCM message. Makes no
 * server-side FCM API call and requires no network.
 *
 * Contains no business logic — it builds a payload and dispatches the bridge
 * event that a real native message would.
 */
final class TestNotificationCommand extends Command
{
    protected $signature = 'firebase-push:test {token} {--title=Test notification} {--body=} {--url=}';

    protected $description = 'Simulate an inbound FCM notification locally to exercise your handlers (no server-side FCM call).';

    public function handle(Dispatcher $events): int
    {
        $payload = array_filter([
            'id' => (string) Str::uuid(),
            'title' => $this->option('title'),
            'body' => $this->option('body'),
            'link' => $this->option('url'),
            'data' => ['token' => (string) $this->argument('token')],
        ], static fn (mixed $value): bool => $value !== null);

        $events->dispatch(new NativeNotificationReceived($payload));

        $this->info('Dispatched a simulated notification through the bridge.');

        return self::SUCCESS;
    }
}
