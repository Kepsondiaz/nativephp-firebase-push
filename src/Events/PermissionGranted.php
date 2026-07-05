<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Events;

/**
 * Dispatched when the user grants notification permission.
 * Dispatched exclusively by FirebasePushManager.
 */
final readonly class PermissionGranted {}
