<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Answer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Answer::class)]
final class AnswerTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Answer(stdClass::class);

        self::assertSame(stdClass::class, $attribute->queryClass);
    }

    public function testInstantiateWithDefaults(): void
    {
        $attribute = new Answer();

        self::assertNull($attribute->queryClass);
    }
}
