<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\ActionRunner;

use Closure;
use Patchlevel\EventSourcing\Console\Tui\SubscriptionAction;
use Revolt\EventLoop;

use function array_filter;
use function array_map;
use function array_shift;
use function array_values;
use function end;
use function explode;
use function fclose;
use function is_resource;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function rewind;
use function sprintf;
use function stream_get_contents;
use function tmpfile;
use function trim;

/**
 * Executes the actions with the existing subscription cli commands in a separate process,
 * so the dashboard keeps rendering while e.g. a long boot is running.
 *
 * The process is polled from the event loop instead of waiting on pipes, which also works on windows.
 *
 * @experimental
 */
final class ProcessActionRunner implements ActionRunner
{
    public const COMMANDS = [
        'event-sourcing:subscription:setup',
        'event-sourcing:subscription:boot',
        'event-sourcing:subscription:run',
        'event-sourcing:subscription:pause',
        'event-sourcing:subscription:reactivate',
        'event-sourcing:subscription:refresh',
        'event-sourcing:subscription:teardown',
        'event-sourcing:subscription:remove',
    ];

    /**
     * @param list<string>      $console      the command to start the console, e.g. [PHP_BINARY, 'bin/console']
     * @param positive-int|null $messageLimit
     */
    public function __construct(
        private readonly array $console,
        private readonly int|null $messageLimit = null,
        private readonly float $pollInterval = 0.1,
    ) {
    }

    /**
     * @param list<string>                $ids
     * @param Closure(list<string>): void $onFinish
     */
    public function run(SubscriptionAction $action, array $ids, Closure $onFinish): void
    {
        $options = [
            ...array_map(static fn (string $id) => '--id=' . $id, $ids),
            '--no-interaction',
        ];

        $queue = array_map(
            static fn (array $arguments) => [...$arguments, ...$options],
            $this->arguments($action),
        );

        $this->next($queue, $onFinish);
    }

    /** @return list<list<string>> */
    private function arguments(SubscriptionAction $action): array
    {
        $limit = $this->messageLimit === null ? [] : ['--message-limit=' . $this->messageLimit];

        return match ($action) {
            SubscriptionAction::Setup => [['event-sourcing:subscription:setup']],
            SubscriptionAction::Boot => [['event-sourcing:subscription:boot', ...$limit]],
            SubscriptionAction::Run => [['event-sourcing:subscription:run', '--run-limit=1', '--sleep=0', ...$limit]],
            SubscriptionAction::Pause => [['event-sourcing:subscription:pause']],
            SubscriptionAction::Reactivate => [['event-sourcing:subscription:reactivate']],
            SubscriptionAction::Refresh => [['event-sourcing:subscription:refresh']],
            SubscriptionAction::Teardown => [['event-sourcing:subscription:teardown']],
            SubscriptionAction::Remove => [['event-sourcing:subscription:remove', '--force']],
            SubscriptionAction::Rebuild => [
                ['event-sourcing:subscription:remove', '--force'],
                ['event-sourcing:subscription:boot', '--setup', ...$limit],
            ],
        };
    }

    /**
     * @param list<list<string>>          $queue
     * @param Closure(list<string>): void $onFinish
     */
    private function next(array $queue, Closure $onFinish): void
    {
        $arguments = array_shift($queue);

        if ($arguments === null) {
            $onFinish([]);

            return;
        }

        $output = tmpfile();

        if ($output === false) {
            $onFinish(['Could not create a temporary file for the command output']);

            return;
        }

        $process = proc_open([...$this->console, ...$arguments], [0 => ['pipe', 'r'], 1 => $output, 2 => $output], $pipes);

        if (!is_resource($process)) {
            fclose($output);
            $onFinish([sprintf('Could not start "%s"', $arguments[0])]);

            return;
        }

        fclose($pipes[0]);

        EventLoop::repeat($this->pollInterval, function (string $callbackId) use ($process, $output, $arguments, $queue, $onFinish): void {
            $status = proc_get_status($process);

            if ($status['running']) {
                return;
            }

            EventLoop::cancel($callbackId);
            proc_close($process);

            rewind($output);
            $content = (string)stream_get_contents($output);
            fclose($output);

            if ($status['exitcode'] !== 0) {
                $onFinish([
                    sprintf(
                        '"%s" exited with code %d%s',
                        $arguments[0],
                        $status['exitcode'],
                        self::lastLine($content) === '' ? '' : ': ' . self::lastLine($content),
                    ),
                ]);

                return;
            }

            $this->next($queue, $onFinish);
        });
    }

    private static function lastLine(string $output): string
    {
        $lines = array_values(array_filter(
            array_map(trim(...), explode("\n", $output)),
            static fn (string $line) => $line !== '',
        ));

        return $lines === [] ? '' : (string)end($lines);
    }
}
