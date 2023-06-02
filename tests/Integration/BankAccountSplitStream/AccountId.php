<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream;

final class AccountId
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
