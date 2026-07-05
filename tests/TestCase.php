<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Tests;

use Kepson\NativePhpFirebasePush\FirebasePushServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FirebasePushServiceProvider::class,
        ];
    }
}
