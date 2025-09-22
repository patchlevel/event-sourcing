<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Stream;
use RuntimeException;

use function count;
use function iterator_to_array;
use function sprintf;

/** @require-extends PHPUnit\Framework\Assert */
trait PhpunitHelper
{
    /** @param list<Message> $expectedMessages */
    public static function assertStreamEquals(array $expectedMessages, Stream $stream): void
    {
        $index = 0;

        $messages = iterator_to_array($stream);

        self::assertEquals(count($expectedMessages), count($messages), 'Expected and actual message count do not match');

        foreach ($messages as $message) {
            $expectedMessage = $expectedMessages[$index] ?? null;

            if ($expectedMessage === null) {
                throw new RuntimeException(sprintf('Expected message at index %d not found', $index));
            }

            self::assertEquals(
                $expectedMessage->header(StreamNameHeader::class)->streamName,
                $message->header(StreamNameHeader::class)->streamName,
            );

            self::assertEquals(
                $expectedMessage->event(),
                $message->event(),
                sprintf('Event at index %d does not match expected event', $index),
            );

            ++$index;
        }
    }
}
