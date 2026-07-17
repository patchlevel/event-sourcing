<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\SensitiveData\Processor;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Tests\Integration\SensitiveData\Events\PersonalDataRemoved;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;

#[Processor('delete_personal_data')]
final class DeletePersonalDataProcessor
{
    public function __construct(
        private readonly CipherKeyStore $cipherKeyStore,
    ) {
    }

    #[Subscribe(PersonalDataRemoved::class)]
    public function handleProfileCreated(PersonalDataRemoved $event): void
    {
        $this->cipherKeyStore->removeWithSubjectId($event->profileId->toString());
    }
}
