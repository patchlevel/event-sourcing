<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Attribute\SplitStream;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\AccountId;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Normalizer\AccountIdNormalizer;

#[Event('bank_account.month_passed')]
#[SplitStream]
final class MonthPassed
{
    public function __construct(
        #[Normalize(new AccountIdNormalizer())]
        public AccountId $accountId,
        public string $name,
        public int $balanceInCents,
    ) {
    }
}
