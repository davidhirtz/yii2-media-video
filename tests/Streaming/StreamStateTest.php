<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Streaming;

use DateTimeImmutable;
use Hirtz\Media\Video\Streaming\StreamState;
use Hirtz\Media\Video\Streaming\StreamStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StreamStateTest extends TestCase
{
    public function testTheStateRoundTripsThroughAnArray(): void
    {
        $state = new StreamState(
            'bunny',
            'a1b2-ç',
            StreamStatus::Failed,
            'Ошибка 🎬 Mu' . "\u{308}" . 'ller',
            ['library' => 42, 'title' => '動画'],
            new DateTimeImmutable('2026-10-30 08:15:00+02:00'),
            scope: '794367',
        );

        $array = $state->toArray();
        $copy = StreamState::fromArray(json_decode(json_encode($array, JSON_THROW_ON_ERROR), true));

        self::assertSame('2026-10-30T06:15:00+00:00', $array['submittedAt']);
        self::assertNotNull($copy);
        self::assertSame($array, $copy->toArray());
        self::assertSame('794367', $copy->withStatus(StreamStatus::Ready)->scope);
        self::assertSame($array, json_decode(json_encode($state, JSON_THROW_ON_ERROR), true));
    }

    public function testAStateWithoutAnIdIsLeftForTheConsole(): void
    {
        $state = StreamState::fromArray(['provider' => 'fake', 'status' => 'pending']);

        self::assertNull($state?->id);
        self::assertSame(StreamStatus::Pending, $state?->status);
        self::assertArrayNotHasKey('id', $state->toArray());
    }

    public function testReadyKeepsItsFirstTime(): void
    {
        $state = new StreamState('fake', 'id', StreamStatus::Processing);
        $ready = $state->withStatus(StreamStatus::Ready);

        self::assertNull($state->readyAt);
        self::assertNotNull($ready->readyAt);
        self::assertEquals($ready->readyAt, $ready->withStatus(StreamStatus::Ready)->readyAt);
        self::assertNull($ready->withStatus(StreamStatus::Failed, 'Gone')->readyAt);
        self::assertEquals($state->submittedAt, $ready->submittedAt);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedProvider(): array
    {
        return [
            'not an array' => ['ready'],
            'no provider' => [['id' => 'x', 'status' => 'ready']],
            'empty provider' => [['provider' => '', 'id' => 'x', 'status' => 'ready']],
            'unknown status' => [['provider' => 'fake', 'id' => 'x', 'status' => 'done']],
            'numeric id' => [['provider' => 'fake', 'id' => 5, 'status' => 'ready']],
            'array error' => [['provider' => 'fake', 'id' => 'x', 'status' => 'failed', 'error' => ['x']]],
            'scalar data' => [['provider' => 'fake', 'id' => 'x', 'status' => 'ready', 'data' => 'x']],
            'bad date' => [['provider' => 'fake', 'id' => 'x', 'status' => 'ready', 'readyAt' => 'gestern']],
        ];
    }

    #[DataProvider('malformedProvider')]
    public function testAMalformedArrayReadsAsNoState(mixed $values): void
    {
        self::assertNull(StreamState::fromArray($values));
    }
}
