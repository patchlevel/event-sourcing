<?php

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Throwable;

final readonly class TaggableDoctrineDbalStoreAdapter implements StoreAdapter
{
    public function __construct(
        private TaggableDoctrineDbalStore $store,
    ) {
    }

    public function load(string $stream, int|null $fromPlayhead = null): Stream
    {
        $criteria = new Criteria(
            new StreamCriterion($stream),
            new ArchivedCriterion(false),
        );

        if ($fromPlayhead !== null) {
            $criteria->add(new FromPlayheadCriterion($fromPlayhead));
        }

        return $this->store->load($criteria);
    }

    public function count(string $stream): int
    {
        $criteria = new Criteria(
            new StreamCriterion($stream),
        );

        return $this->store->count($criteria);
    }

    /**
     * @param iterable<Message> $messages
     */
    public function write(string $stream, iterable $messages): void
    {
        $archiveTo = null;

        $this->store->save(
            ...array_map(
                static function (Message $message) use (
                    $stream,
                    &$archiveTo
                ) {
                    if ($message->hasHeader(StreamStartHeader::class)) {
                        try {
                            $archiveTo = $message->header(PlayheadHeader::class)->playhead;
                        } catch (Throwable) {
                        }
                    }

                    return $message->withHeader(new StreamNameHeader($stream));
                },
                $messages,
            )
        );

        if ($archiveTo === null) {
            $this->store->save(...$messages);

            return;
        }

        $this->store->transactional(
            static function () use ($stream, $archiveTo, $messages): void {
                $this->store->save(...$messages);

                $this->store->archive(
                    new Criteria(
                        new StreamCriterion($stream),
                        new ToPlayheadCriterion($archiveTo),
                    ),
                );
            }
        );
    }
}