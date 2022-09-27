<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Normalizer;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\AccountId;

use function is_string;

final class AccountIdNormalizer implements Normalizer
{
    public function normalize(mixed $value): string
    {
        if (!$value instanceof AccountId) {
            throw new InvalidArgumentException();
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): ?AccountId
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException();
        }

        return AccountId::fromString($value);
    }
}
