<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Commands;

use Illuminate\Console\Command;
use Kepson\NativePhpFirebasePush\Contracts\FirebasePushManager;

/**
 * Outputs the current device FCM registration token. Development/debugging aid.
 * Contains no business logic — delegates to the manager.
 */
final class TokenCommand extends Command
{
    protected $signature = 'firebase-push:token';

    protected $description = 'Output the current device FCM registration token.';

    public function handle(FirebasePushManager $push): int
    {
        $token = $push->token();

        if ($token === null) {
            $this->warn('No FCM token is available yet.');

            return self::SUCCESS;
        }

        $this->line($token);

        return self::SUCCESS;
    }
}
