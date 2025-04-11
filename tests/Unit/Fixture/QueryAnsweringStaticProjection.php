<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Answer;

final class QueryAnsweringStaticProjection
{
    #[Answer]
    public static function answerQuery(QueryProfile $queryProfile): string
    {
        return 'found static';
    }
}
