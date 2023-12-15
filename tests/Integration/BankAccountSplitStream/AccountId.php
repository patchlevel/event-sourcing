<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

final class AccountId implements AggregateRootId
{
    private function __construct(
        private string $id,
    ) {
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function toString(): string
    {
        return $this->id;
    }
}
