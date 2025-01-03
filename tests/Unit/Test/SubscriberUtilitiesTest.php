<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Test;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Test\SubscriberUtilities;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

final class SubscriberUtilitiesTest extends TestCase
{
    public function testRun(): void
    {
        $subscriber = new #[Projector('test')] class {
            public int $called = 0;

            #[Subscribe(ProfileCreated::class)]
            public function run(): void
            {
                $this->called++;
            }
        };

        $test = $this->getTester();
        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->executeRun($subscriber);

        self::assertSame(1, $subscriber->called);
    }
    public function testRunNotFound(): void
    {
        $subscriber = new #[Projector('test')] class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $test = $this->getTester();
        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->executeRun($subscriber);

        self::assertSame(0, $subscriber->called);
    }

    public function testSetup(): void
    {
        $subscriber = new #[Projector('test')] class {
            public int $called = 0;

            #[Setup]
            public function run(): void
            {
                $this->called++;
            }
        };

        $test = $this->getTester();
        $test->executeSetup($subscriber);

        self::assertSame(1, $subscriber->called);
    }

    public function testSetupNotFound(): void
    {
        $subscriber = new #[Projector('test')] class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $test = $this->getTester();
        $test->executeSetup($subscriber);

        self::assertSame(0, $subscriber->called);
    }

    public function testTeardown(): void
    {
        $subscriber = new #[Projector('test')] class {
            public int $called = 0;

            #[Teardown]
            public function run(): void
            {
                $this->called++;
            }
        };

        $test = $this->getTester();
        $test->executeTeardown($subscriber);

        self::assertSame(1, $subscriber->called);
    }

    public function testTeardownNotFound(): void
    {
        $subscriber = new #[Projector('test')] class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $test = $this->getTester();
        $test->executeTeardown($subscriber);

        self::assertSame(0, $subscriber->called);
    }

    public function getTester(): TestCase
    {
        return new class($this->name()) extends TestCase {
            use SubscriberUtilities;
        };
    }
}
