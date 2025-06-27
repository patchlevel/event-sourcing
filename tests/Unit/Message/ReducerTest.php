<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Reducer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(Reducer::class)]
final class ReducerTest extends TestCase
{
    public function testEmpty(): void
    {
        $reducer = new Reducer();
        $state = $reducer->reduce([]);

        self::assertSame([], $state);
    }

    public function testWithMessages(): void
    {
        $messages = $this->messages();

        $reducer = new Reducer();
        $state = $reducer->reduce($messages);

        self::assertSame([], $state);
    }

    public function testInitState(): void
    {
        $state = (new Reducer())
            ->initState(['count' => 0])
            ->reduce([]);

        self::assertSame(['count' => 0], $state);
    }

    public function testAny(): void
    {
        $messages = $this->messages();

        $state = (new Reducer())
            ->any(static function (Message $message, array $state): array {
                return [...$state, $message];
            })
            ->reduce($messages);

        self::assertSame($messages, $state);
    }

    public function testWhen(): void
    {
        $messages = $this->messages();

        $state = (new Reducer())
            ->when(
                ProfileCreated::class,
                static function (Message $message, array $state): array {
                    return [...$state, $message];
                },
            )
            ->reduce($messages);

        self::assertSame([$messages[0], $messages[3]], $state);
    }

    public function testMatch(): void
    {
        $messages = $this->messages();

        $state = (new Reducer())
            ->match([
                ProfileCreated::class => static function (Message $message, array $state): array {
                        return [...$state, $message];
                },
            ])
            ->reduce($messages);

        self::assertSame([$messages[0], $messages[3]], $state);
    }

    public function testFinalize(): void
    {
        $messages = $this->messages();

        $state = (new Reducer())
            ->any(static function (Message $message, array $state): array {
                return [...$state, $message];
            })
            ->finalize(static function (array $state): array {
                return [
                    'count' => count($state),
                    'messages' => $state,
                ];
            })
            ->reduce($messages);

        self::assertSame([
            'count' => 5,
            'messages' => $messages,
        ], $state);
    }

    /** @return list<Message> */
    private function messages(): array
    {
        return [
            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '1',
                    1,
                    new DateTimeImmutable(),
                )),
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '1',
                    2,
                    new DateTimeImmutable(),
                )),
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '1',
                    3,
                    new DateTimeImmutable(),
                )),

            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '2',
                    1,
                    new DateTimeImmutable(),
                )),

            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('2'),
                ),
            )
                ->withHeader(new AggregateHeader(
                    'profile',
                    '2',
                    2,
                    new DateTimeImmutable(),
                )),
        ];
    }
}
