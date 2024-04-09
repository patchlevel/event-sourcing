<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\PersonalData\Processor;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Tests\Integration\PersonalData\Events\PersonalDataRemoved;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;

#[Processor('delete_personal_data')]
final class DeletePersonalDataProcessor
{
    public function __construct(
        private readonly CipherKeyStore $cipherKeyStore,
    ) {
    }

    #[Subscribe(PersonalDataRemoved::class)]
    public function handleProfileCreated(Message $message): void
    {
        $aggregateId = $message->header(AggregateHeader::class)->aggregateId;

        $this->cipherKeyStore->remove($aggregateId);
    }
}
