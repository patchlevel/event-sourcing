<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Normalize;

#[Aggregate('wrong_normalizer')]
class WrongNormalizerAggregate extends AggregateRoot
{
    #[Normalize(new EmailNormalizer())]
    public bool $email = true;

    public function aggregateRootId(): string
    {
        return '1';
    }
}
