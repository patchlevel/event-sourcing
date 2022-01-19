<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function sprintf;

final class DuplicateApplyMethod extends AggregateException
{
    /**
     * @param class-string<AggregateRoot>    $aggregate
     * @param class-string<AggregateChanged> $event
     */
    public function __construct(string $aggregate, string $event, string $fistMethod, string $secondMethod)
    {
        parent::__construct(
            sprintf(
                'Two methods "%s" and "%s" on the aggregate "%s" want to apply the same event "%s".',
                $fistMethod,
                $secondMethod,
                $aggregate,
                $event
            )
        );
    }
}
