<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Streaming;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use JsonSerializable;
use Override;
use Stringable;

/**
 * Encoded as {@see toArray()}, so the trail stores what the column does; an agent reads it as a string.
 */
final readonly class StreamState implements JsonSerializable, Stringable
{
    public DateTimeImmutable $submittedAt;
    public ?DateTimeImmutable $readyAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $provider,
        public ?string $id,
        public StreamStatus $status = StreamStatus::Pending,
        public ?string $error = null,
        public array $data = [],
        ?DateTimeImmutable $submittedAt = null,
        ?DateTimeImmutable $readyAt = null,
    ) {
        $utc = new DateTimeZone('UTC');

        $this->submittedAt = ($submittedAt ?? new DateTimeImmutable())->setTimezone($utc);
        $this->readyAt = $readyAt?->setTimezone($utc);
    }

    public static function failed(string $provider, string $error, ?string $id = null): self
    {
        return new self($provider, $id, StreamStatus::Failed, $error);
    }

    public function withStatus(StreamStatus $status, ?string $error = null): self
    {
        $readyAt = $status === StreamStatus::Ready
            ? $this->readyAt ?? new DateTimeImmutable()
            : null;

        return new self($this->provider, $this->id, $status, $error, $this->data, $this->submittedAt, $readyAt);
    }

    public function isReady(): bool
    {
        return $this->status === StreamStatus::Ready;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'provider' => $this->provider,
            'id' => $this->id,
            'status' => $this->status->value,
            'error' => $this->error,
            'data' => $this->data ?: null,
            'submittedAt' => $this->submittedAt->format(DateTimeInterface::ATOM),
            'readyAt' => $this->readyAt?->format(DateTimeInterface::ATOM),
        ], static fn (mixed $value): bool => $value !== null);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->error !== null ? "{$this->status->value}: $this->error" : $this->status->value;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(mixed $values): ?self
    {
        if (!is_array($values)) {
            return null;
        }

        $provider = $values['provider'] ?? null;
        $id = $values['id'] ?? null;
        $status = is_string($values['status'] ?? null) ? StreamStatus::tryFrom($values['status']) : null;
        $error = $values['error'] ?? null;
        $data = $values['data'] ?? [];

        if (
            !is_string($provider)
            || $provider === ''
            || ($id !== null && (!is_string($id) || $id === ''))
            || $status === null
            || ($error !== null && !is_string($error))
            || !is_array($data)
        ) {
            return null;
        }

        try {
            $submittedAt = self::parseDate($values['submittedAt'] ?? null);
            $readyAt = self::parseDate($values['readyAt'] ?? null);
        } catch (Exception) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return new self($provider, $id, $status, $error, $data, $submittedAt, $readyAt);
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new Exception('Not a date');
        }

        return new DateTimeImmutable($value);
    }
}
