<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\DecisionModel;

use Patchlevel\EventSourcing\CommandBus\Handler\ServiceNotResolvable;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\DecisionModel\DecisionFactory;
use Patchlevel\EventSourcing\DecisionModel\DecisionParameterResolver;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function iterator_to_array;

#[CoversClass(DecisionParameterResolver::class)]
final class DecisionParameterResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $handler = new class () {
            public function __invoke(CreateProfile $command, Decision $decision): void
            {
            }
        };

        $command = new CreateProfile(ProfileId::fromString('1'), 'info');

        $resolver = new DecisionParameterResolver(new DecisionFactory(new InMemoryStore()));
        $arguments = iterator_to_array($resolver->resolve(new ReflectionMethod($handler, '__invoke'), $command));

        self::assertCount(2, $arguments);
        self::assertSame($command, $arguments[0]);
        self::assertInstanceOf(Decision::class, $arguments[1]);
    }

    public function testNewDecisionForEachCall(): void
    {
        $handler = new class () {
            public function __invoke(CreateProfile $command, Decision $decision): void
            {
            }
        };

        $command = new CreateProfile(ProfileId::fromString('1'), 'info');
        $method = new ReflectionMethod($handler, '__invoke');

        $resolver = new DecisionParameterResolver(new DecisionFactory(new InMemoryStore()));

        self::assertNotSame(
            iterator_to_array($resolver->resolve($method, $command))[1],
            iterator_to_array($resolver->resolve($method, $command))[1],
        );
    }

    public function testUnsupportedParameter(): void
    {
        $handler = new class () {
            public function __invoke(CreateProfile $command, string $foo): void
            {
            }
        };

        $resolver = new DecisionParameterResolver(new DecisionFactory(new InMemoryStore()));

        $this->expectException(ServiceNotResolvable::class);

        iterator_to_array($resolver->resolve(
            new ReflectionMethod($handler, '__invoke'),
            new CreateProfile(ProfileId::fromString('1'), 'info'),
        ));
    }
}
