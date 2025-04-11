<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Answer;

final class QueryAnsweringProjection
{
    #[Answer]
    public function answerQuery(QueryProfile $queryProfile): string
    {
        return 'found';
    }
}
