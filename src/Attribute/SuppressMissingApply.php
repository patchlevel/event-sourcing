<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function is_string;

#[Attribute(Attribute::TARGET_CLASS)]
class SuppressMissingApply
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
            throw new InvalidArgumentException(
                'The value should either be an array of aggregate changed classes, or a "*" for all events.'
            );
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
