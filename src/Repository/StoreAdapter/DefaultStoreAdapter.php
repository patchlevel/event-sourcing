<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStartHeader;

final class DefaultStoreAdapter implements StoreAdapter
{
    public function __construct(
        private readonly Store $store,
    ) {
    }

    public function load(string $streamName, int|null $fromPlayhead = null): Stream
    {
        if ($fromPlayhead === null) {
            return $this->store->load(new Criteria(
                new StreamCriterion($streamName),
                new ArchivedCriterion(false),
            ));
        }

        return $this->store->load(new Criteria(
            new StreamCriterion($streamName),
            new FromPlayheadCriterion($fromPlayhead),
        ));
    }

    public function has(string $streamName): bool
    {
        return $this->store->count(new Criteria(new StreamCriterion($streamName))) > 0;
    }

    public function save(string $streamName, Message ...$messages): void
    {
        $archiveTo = null;

        foreach ($messages as $message) {
            if (!$message->hasHeader(StreamStartHeader::class)) {
                continue;
            }

            $archiveTo = $message->header(PlayheadHeader::class)->playhead;
        }

        if ($archiveTo === null) {
            $this->store->save(...$messages);

            return;
        }

        $this->store->transactional(
            function () use ($messages, $streamName, $archiveTo): void {
                $this->store->save(...$messages);
                $this->store->archive(
                    new Criteria(
                        new StreamCriterion($streamName),
                        new ToPlayheadCriterion($archiveTo),
                    ),
                );
            },
        );
    }
}
