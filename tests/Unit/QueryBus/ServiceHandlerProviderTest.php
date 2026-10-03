<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\QueryBus;

use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\QueryBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandlerProvider::class)]
final class ServiceHandlerProviderTest extends TestCase
{
    public function testEmpty(): void
    {
        $provider = new ServiceHandlerProvider([]);
        $result = $provider->handlerForQuery(QueryProfile::class);

        self::assertCount(0, $result);
    }

    public function testFindHandler(): void
    {
        $class = new class () {
            #[Answer(QueryProfile::class)]
            public function handle(): void
            {
            }
        };

        $provider = new ServiceHandlerProvider([$class]);

        $result = $provider->handlerForQuery(QueryProfile::class);

        self::assertCount(1, $result);
        self::assertEquals($class->handle(...), $result[0]->callable());
    }

    public function testFindStaticHandler(): void
    {
        $class = new class () {
            #[Answer(QueryProfile::class)]
            public static function handle(): void
            {
            }
        };

        $provider = new ServiceHandlerProvider([$class]);

        $result = $provider->handlerForQuery(QueryProfile::class);

        self::assertCount(1, $result);
        self::assertEquals($class::handle(...), $result[0]->callable());
    }
}
