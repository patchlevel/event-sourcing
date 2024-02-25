<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\AccountId;

#[Event('bank_account.created')]
final class BankAccountCreated
{
    public function __construct(
        #[IdNormalizer]
        public AccountId $accountId,
        public string $name,
    ) {
    }
}
