<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Test;

use Closure;
use Generator;
use Patchlevel\EventSourcing\Test\AggregateAlreadySet;
use Patchlevel\EventSourcing\Test\AggregateRootTestCase;
use Patchlevel\EventSourcing\Test\NoAggregateCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AggregateRootTestCaseTest extends TestCase
{
    public function testException(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->throwException(),
            )
            ->expectsException(RuntimeException::class);

        $test->assert();
    }

    public function testExceptionMessage(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->throwException(),
            )
            ->expectsExceptionMessage('throwing so that you can catch it!');

        $test->assert();
    }

    public function testExceptionUncatched(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->throwException(),
            );

        $this->expectException(RuntimeException::class);
        $test->assert();
    }

    public function testVisited(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
            );

        $test->assert();
    }

    public function testVisitedDoubleAssert(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
            );

        $test->assert();
        $test->assert();
    }

    public function testCreation(): void
    {
        $test = $this->getTester();

        $test
            ->when(
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            )
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            );

        $test->assert();
    }

    public function testCreationWithEmptyGiven(): void
    {
        $test = $this->getTester();

        $test
            ->given()
            ->when(
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            )
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            );

        $test->assert();
    }

    public function testMissingGiven(): void
    {
        $test = $this->getTester();

        $test
            ->when(
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            )
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            );

        $test->assert();
    }

    public function testNoGivenAndNoCreation(): void
    {
        $test = $this->getTester();

        $test
            ->when(
                static fn () => 'no aggregate as return',
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
            );

        $this->expectException(NoAggregateCreated::class);
        $test->assert();
    }

    public function testDoubleAggregateCreation(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            )
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            );

        $this->expectException(AggregateAlreadySet::class);
        $test->assert();
    }

    /** @return Generator<array{array<object>, array<Closure>, array<object>}> */
    public static function provideVariousTestCases(): iterable
    {
        yield [
            [
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            ],
            [
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
            ],
            [
                new ProfileVisited(ProfileId::fromString('2')),
            ],
        ];

        yield [
            [],
            [
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            ],
            [
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            ],
        ];
    }

    /**
     * @param array<object>  $givenEvents
     * @param array<Closure> $whens
     * @param array<object>  $expectedEvents
     */
    #[DataProvider('provideVariousTestCases')]
    public function testWithDataProvider(array $givenEvents, array $whens, array $expectedEvents): void
    {
        $test = $this->getTester();

        $test
            ->given(...$givenEvents)
            ->when(...$whens)
            ->then(...$expectedEvents);

        $test->assert();
    }

    public function getTester(): AggregateRootTestCase
    {
        return new class($this->name()) extends AggregateRootTestCase {
            protected function aggregateClass(): string
            {
                return Profile::class;
            }
        };
    }
}
