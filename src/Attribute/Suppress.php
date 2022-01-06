<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function is_string;

#[Attribute(Attribute::TARGET_CLASS)]
class Suppress
{
    public const ALL = '*';

    /** @var list<class-string<AggregateChanged>> */
    private array $suppressEvents = [];
    private bool $suppressAll = false;

    /**
     * @param list<class-string<AggregateChanged>>|string $suppress
     */
    public function __construct(string|array $suppress)
    {
        if ($suppress === '*') {
            $this->suppressAll = true;

            return;
        }

        if (is_string($suppress)) {
            throw new InvalidArgumentException("list<class-string<AggregateChanged>>|'*'");
        }

        $this->suppressEvents = $suppress;
    }

    /**
     * @return list<class-string<AggregateChanged>>
     */
    public function suppressEvents(): array
    {
        return $this->suppressEvents;
    }

    public function suppressAll(): bool
    {
        return $this->suppressAll;
    }
}
