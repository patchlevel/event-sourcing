<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\View;

use Patchlevel\EventSourcing\Console\Tui\Theme;

use function array_slice;
use function count;
use function max;
use function min;
use function sprintf;

/**
 * A scrollable block of text, used for the describe and help views.
 *
 * @experimental
 */
final class TextView implements View
{
    private int $offset = 0;
    private int $pageSize = 1;

    /** @param list<string> $lines */
    public function __construct(
        private string $title,
        private array $lines = [],
    ) {
    }

    /** @param list<string> $lines */
    public function update(string $title, array $lines): void
    {
        $this->title = $title;
        $this->lines = $lines;
        $this->offset = min($this->offset, $this->maxOffset());
    }

    public function title(): string
    {
        return Theme::bold(Theme::ACCENT, $this->title);
    }

    public function label(): string
    {
        if (count($this->lines) <= $this->pageSize) {
            return '';
        }

        return Theme::color(Theme::MUTED, sprintf(
            '%d–%d of %d',
            $this->offset + 1,
            min(count($this->lines), $this->offset + $this->pageSize),
            count($this->lines),
        ));
    }

    /** @return list<string> */
    public function render(int $columns, int $rows): array
    {
        $this->pageSize = max(1, $rows);
        $this->offset = min($this->offset, $this->maxOffset());

        return array_slice($this->lines, $this->offset, $this->pageSize);
    }

    public function moveBy(int $delta): void
    {
        $this->offset = max(0, min($this->maxOffset(), $this->offset + $delta));
    }

    public function pageUp(): void
    {
        $this->moveBy(-$this->pageSize);
    }

    public function pageDown(): void
    {
        $this->moveBy($this->pageSize);
    }

    public function moveToStart(): void
    {
        $this->offset = 0;
    }

    public function moveToEnd(): void
    {
        $this->offset = $this->maxOffset();
    }

    private function maxOffset(): int
    {
        return max(0, count($this->lines) - $this->pageSize);
    }
}
