<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Aggregate;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\AggregateId;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\AccountId;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BalanceAdded;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BankAccountCreated;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\MonthPassed;

#[Aggregate('profile')]
final class BankAccount extends BasicAggregateRoot
{
    #[AggregateId]
    private AccountId $id;
    private string $name;
    private int $balanceInCents;
    /** @var list<object> */
    public array $appliedEvents = [];

    public static function create(AccountId $id, string $name): self
    {
        $self = new self();
        $self->recordThat(new BankAccountCreated($id, $name));

        return $self;
    }

    /** @param positive-int $newAddedBalance */
    public function addBalance(int $newAddedBalance): void
    {
        $this->recordThat(new BalanceAdded($this->id, $newAddedBalance));
    }

    public function beginNewMonth(): void
    {
        $this->recordThat(new MonthPassed($this->id, $this->name, $this->balanceInCents));
    }

    #[Apply(BankAccountCreated::class)]
    protected function applyBankAccountCreated(BankAccountCreated $event): void
    {
        $this->id = $event->accountId;
        $this->name = $event->name;
        $this->balanceInCents = 0;
        $this->appliedEvents[] = $event;
    }

    #[Apply(BalanceAdded::class)]
    protected function applyBalanceAdded(BalanceAdded $event): void
    {
        $this->balanceInCents += $event->balanceInCents;
        $this->appliedEvents[] = $event;
    }

    #[Apply(MonthPassed::class)]
    protected function applyMonthPassed(MonthPassed $event): void
    {
        $this->id = $event->accountId;
        $this->name = $event->name;
        $this->balanceInCents = $event->balanceInCents;
        $this->appliedEvents[] = $event;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function balance(): int
    {
        return $this->balanceInCents;
    }
}
