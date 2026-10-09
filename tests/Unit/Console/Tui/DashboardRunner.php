<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Tui;

use Closure;
use Revolt\EventLoop;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

/**
 * Feeds key presses into a virtual terminal while the dashboard runs
 * and captures the screen at the end.
 */
final class DashboardRunner
{
    private const STEP = 0.02;

    public readonly VirtualTerminal $terminal;
    private string $screen = '';

    public function __construct(
        private readonly int $columns = 140,
        private readonly int $rows = 30,
    ) {
        $this->terminal = new VirtualTerminal($columns, $rows);
    }

    /**
     * @param Closure(): mixed $run    starts the (blocking) dashboard
     * @param list<string>     $inputs
     */
    public function run(Closure $run, array $inputs = []): string
    {
        $delay = self::STEP;

        foreach ($inputs as $input) {
            EventLoop::delay($delay, fn () => $this->terminal->simulateInput($input));
            $delay += self::STEP;
        }

        EventLoop::delay($delay + self::STEP, function (): void {
            $buffer = new ScreenBuffer($this->columns, $this->rows);
            $buffer->write($this->terminal->getOutput());

            $this->screen = $buffer->getScreen();

            $this->terminal->simulateInput("\x03");
        });

        $run();

        return $this->screen;
    }
}
