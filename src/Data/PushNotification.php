<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Data;

use Carbon\CarbonImmutable;

/**
 * Immutable value object representing a single FCM notification as delivered to
 * the device. Carries no business logic. All construction from a raw bridge
 * payload is isolated to {@see self::fromBridgePayload()} so the rest of the
 * codebase never touches raw array keys.
 */
final readonly class PushNotification
{
    /**
     * @param  array<string, string>  $data  Arbitrary key/value data payload from FCM.
     */
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $body,
        public ?string $imageUrl,
        public ?string $link,
        public array $data,
        public ?CarbonImmutable $sentAt,
        public CarbonImmutable $receivedAt,
        public ?string $channel,
        public ?string $collapseKey,
        public bool $tapped,
        public bool $foreground,
    ) {}

    /**
     * Construct the object from a raw inbound bridge payload. Payload keys are
     * camelCase; timestamps are ISO 8601 UTC strings. Internal use only.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromBridgePayload(array $payload): self
    {
        return new self(
            id: isset($payload['id']) ? (string) $payload['id'] : '',
            title: self::nullableString($payload, 'title'),
            body: self::nullableString($payload, 'body'),
            imageUrl: self::nullableString($payload, 'imageUrl'),
            link: self::nullableString($payload, 'link') ?? self::nullableString($payload, 'url'),
            data: self::stringMap($payload['data'] ?? []),
            sentAt: self::nullableTimestamp($payload, 'sentAt'),
            receivedAt: self::nullableTimestamp($payload, 'receivedAt') ?? CarbonImmutable::now('UTC'),
            channel: self::nullableString($payload, 'channel'),
            collapseKey: self::nullableString($payload, 'collapseKey'),
            tapped: (bool) ($payload['tapped'] ?? false),
            foreground: (bool) ($payload['foreground'] ?? false),
        );
    }

    /**
     * @return array{
     *     id: string,
     *     title: ?string,
     *     body: ?string,
     *     imageUrl: ?string,
     *     link: ?string,
     *     data: array<string, string>,
     *     sentAt: ?string,
     *     receivedAt: string,
     *     channel: ?string,
     *     collapseKey: ?string,
     *     tapped: bool,
     *     foreground: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'imageUrl' => $this->imageUrl,
            'link' => $this->link,
            'data' => $this->data,
            'sentAt' => $this->sentAt?->toIso8601String(),
            'receivedAt' => $this->receivedAt->toIso8601String(),
            'channel' => $this->channel,
            'collapseKey' => $this->collapseKey,
            'tapped' => $this->tapped,
            'foreground' => $this->foreground,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function nullableString(array $payload, string $key): ?string
    {
        return isset($payload[$key]) ? (string) $payload[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function nullableTimestamp(array $payload, string $key): ?CarbonImmutable
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value)->utc()
            : null;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = is_scalar($item) ? (string) $item : '';
        }

        return $map;
    }
}
