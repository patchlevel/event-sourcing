<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Tui;

use Patchlevel\EventSourcing\Console\Tui\ActionRunner\ProcessActionRunner;
use Patchlevel\EventSourcing\Console\Tui\SubscriptionAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_BINARY;

#[CoversClass(ProcessActionRunner::class)]
final class ProcessActionRunnerTest extends TestCase
{
    public function testSuccessfulCommands(): void
    {
        // the fake console appends its arguments to a file, so the executed commands can be checked
        $log = (string)tempnam(sys_get_temp_dir(), 'dashboard');
        $runner = new ProcessActionRunner([
            PHP_BINARY,
            '-r',
            'file_put_contents($argv[1], implode(" ", array_slice($argv, 2)) . "\n", FILE_APPEND);',
            '--',
            $log,
        ], 50, 0.01);

        self::assertSame([], $this->runAction($runner, SubscriptionAction::Rebuild, ['profile']));
        self::assertSame(
            "event-sourcing:subscription:remove --force --id=profile --no-interaction\n"
            . "event-sourcing:subscription:boot --setup --message-limit=50 --id=profile --no-interaction\n",
            file_get_contents($log),
        );

        unlink($log);
    }

    public function testFailedCommand(): void
    {
        $runner = new ProcessActionRunner([PHP_BINARY, '-r', 'echo "Something went wrong\n"; exit(3);', '--'], null, 0.01);

        self::assertSame(
            ['"event-sourcing:subscription:pause" exited with code 3: Something went wrong'],
            $this->runAction($runner, SubscriptionAction::Pause, ['profile']),
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function runAction(ProcessActionRunner $runner, SubscriptionAction $action, array $ids): array
    {
        /** @var Suspension<list<string>> $suspension */
        $suspension = EventLoop::getSuspension();
        $runner->run($action, $ids, static fn (array $errors) => $suspension->resume($errors));

        return $suspension->suspend();
    }
}
