<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\AccountId;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Normalizer\AccountIdNormalizer;

#[Event('bank_account.balance_added')]
final class BalanceAdded
{
    public function __construct(
        #[Normalize(new AccountIdNormalizer())]
        public AccountId $accountId,
        /** @var positive-int */
        public int $balanceInCents,
    ) {
    }
}
