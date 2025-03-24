<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(DebugCommand::class)]
final class DebugCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $command = new DebugCommand(
            new AggregateRootRegistry(['profile' => Profile::class]),
            new EventRegistry(['profile.created' => ProfileCreated::class]),
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('profile', $content);
        self::assertStringContainsString(Profile::class, $content);
        self::assertStringContainsString('profile.created', $content);
        self::assertStringContainsString(ProfileCreated::class, $content);
    }

    public function testSuccessfulWithSubscribers(): void
    {
        $subscriber1 = new #[Subscriber('profile.test', RunMode::FromBeginning)]
        class {
        };

        $subscriber2 = new #[Projector('profile.projection')]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(ProfileCreated $event): void
            {
            }
        };

        $command = new DebugCommand(
            new AggregateRootRegistry(['profile' => Profile::class]),
            new EventRegistry(['profile.created' => ProfileCreated::class]),
            new MetadataSubscriberAccessorRepository([$subscriber1, $subscriber2]),
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('default', $content);
        self::assertStringContainsString('projector', $content);
    }
}
