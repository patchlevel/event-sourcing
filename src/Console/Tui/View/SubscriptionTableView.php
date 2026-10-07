<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\View;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Console\Tui\SubscriptionFormatter;
use Patchlevel\EventSourcing\Console\Tui\Theme;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Symfony\Component\Tui\Ansi\AnsiUtils;

use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_sum;
use function array_values;
use function count;
use function implode;
use function in_array;
use function max;
use function min;
use function sprintf;
use function str_contains;
use function strcmp;
use function strtolower;
use function usort;

/**
 * The subscription table with selection, marks and filter.
 *
 * @experimental
 */
final class SubscriptionTableView implements View
{
    private const COLUMNS = ['ID', 'GROUP', 'MODE', 'PROGRESS', 'POSITION', 'LAG', 'STATUS', 'RETRY', 'AGE', 'ERROR'];
    private const RIGHT_ALIGNED = ['POSITION', 'LAG', 'RETRY', 'AGE'];
    private const OPTIONAL = ['RETRY', 'MODE', 'GROUP', 'PROGRESS'];
    private const PROGRESS_BAR_WIDTH = 10;
    private const MIN_ERROR_WIDTH = 16;
    private const GAP = '  ';

    /** @var list<Subscription> */
    private array $subscriptions = [];

    /** @var list<Subscription> */
    private array $visible = [];

    private int|null $head = null;
    private DateTimeImmutable $now;
    private string $filter = '';
    private string|null $selectedId = null;
    private int $offset = 0;
    private int $pageSize = 1;

    /** @var array<string, true> */
    private array $marked = [];

    public function __construct()
    {
        $this->now = new DateTimeImmutable();
    }

    /** @param list<Subscription> $subscriptions */
    public function update(array $subscriptions, int|null $head, DateTimeImmutable $now): void
    {
        usort($subscriptions, static fn (Subscription $a, Subscription $b) => strcmp($a->id(), $b->id()));

        $this->subscriptions = $subscriptions;
        $this->head = $head;
        $this->now = $now;

        $ids = array_map(static fn (Subscription $subscription) => $subscription->id(), $subscriptions);

        foreach (array_keys($this->marked) as $id) {
            if (in_array($id, $ids, true)) {
                continue;
            }

            unset($this->marked[$id]);
        }

        $this->applyFilter();
    }

    public function filter(): string
    {
        return $this->filter;
    }

    public function changeFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->applyFilter();
    }

    /** @return list<Subscription> */
    public function subscriptions(): array
    {
        return $this->subscriptions;
    }

    public function selected(): Subscription|null
    {
        foreach ($this->visible as $subscription) {
            if ($subscription->id() === $this->selectedId) {
                return $subscription;
            }
        }

        return null;
    }

    public function toggleMark(): void
    {
        $selected = $this->selected();

        if ($selected === null) {
            return;
        }

        if (isset($this->marked[$selected->id()])) {
            unset($this->marked[$selected->id()]);
        } else {
            $this->marked[$selected->id()] = true;
        }

        $this->moveBy(1);
    }

    public function hasMarks(): bool
    {
        return $this->marked !== [];
    }

    public function clearMarks(): void
    {
        $this->marked = [];
    }

    /**
     * The subscriptions an action applies to: the marked ones, or the selected one if nothing is marked.
     *
     * @return list<Subscription>
     */
    public function targets(): array
    {
        if ($this->marked === []) {
            $selected = $this->selected();

            return $selected === null ? [] : [$selected];
        }

        return array_values(array_filter(
            $this->subscriptions,
            fn (Subscription $subscription) => isset($this->marked[$subscription->id()]),
        ));
    }

    public function title(): string
    {
        return Theme::bold(Theme::ACCENT, 'Subscriptions');
    }

    public function label(): string
    {
        $parts = [];

        if ($this->filter !== '') {
            $parts[] = Theme::color(Theme::HIGHLIGHT, '/' . $this->filter);
        }

        if ($this->marked !== []) {
            $parts[] = Theme::color(Theme::HIGHLIGHT, sprintf('◆ %d marked', count($this->marked)));
        }

        $parts[] = Theme::color(Theme::MUTED, count($this->visible) === count($this->subscriptions)
            ? (string)count($this->subscriptions)
            : sprintf('%d of %d', count($this->visible), count($this->subscriptions)));

        return implode(Theme::color(Theme::SUBTLE, ' · '), $parts);
    }

    /** @return list<string> */
    public function render(int $columns, int $rows): array
    {
        $this->pageSize = max(1, $rows - 2);

        $cells = array_map($this->cells(...), $this->visible);
        $widths = $this->widths($cells, $columns - 2);

        $lines = [
            '  ' . Theme::color(Theme::MUTED, $this->row(self::COLUMNS, $widths)),
            '',
        ];

        if ($this->visible === []) {
            $lines[] = '  ' . Theme::color(Theme::MUTED, $this->subscriptions === []
                ? 'No subscriptions found'
                : sprintf('No subscriptions match "%s"', $this->filter));

            return $lines;
        }

        $selectedIndex = $this->selectedIndex();

        if ($selectedIndex < $this->offset) {
            $this->offset = $selectedIndex;
        } elseif ($selectedIndex >= $this->offset + $this->pageSize) {
            $this->offset = $selectedIndex - $this->pageSize + 1;
        }

        $this->offset = max(0, min($this->offset, count($this->visible) - $this->pageSize));

        foreach (array_slice($this->visible, $this->offset, $this->pageSize, true) as $index => $subscription) {
            $selected = $index === $selectedIndex;
            $marked = isset($this->marked[$subscription->id()]);

            $gutter = ($selected ? Theme::color(Theme::ACCENT, '▌') : ' ')
                . ($marked ? Theme::color(Theme::HIGHLIGHT, '◆') : ' ');

            $row = $this->row($cells[$index], $widths);

            if (!$selected) {
                $lines[] = $gutter . $row;

                continue;
            }

            $background = "\e[" . Theme::SELECTED_BACKGROUND . 'm';
            $lines[] = $gutter . $background
                . AnsiUtils::reapplyBackgroundAfterResets(Theme::fit($row, $columns - 2), $background)
                . "\e[0m";
        }

        return $lines;
    }

    public function moveBy(int $delta): void
    {
        if ($this->visible === []) {
            return;
        }

        $index = max(0, min(count($this->visible) - 1, $this->selectedIndex() + $delta));
        $this->selectedId = $this->visible[$index]->id();
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
        $this->moveBy(-count($this->visible));
    }

    public function moveToEnd(): void
    {
        $this->moveBy(count($this->visible));
    }

    private function applyFilter(): void
    {
        $needle = strtolower($this->filter);

        $this->visible = array_values(array_filter(
            $this->subscriptions,
            static fn (Subscription $subscription) => $needle === ''
                || str_contains(strtolower($subscription->id()), $needle)
                || str_contains(strtolower($subscription->group()), $needle)
                || str_contains($subscription->status()->value, $needle)
                || str_contains($subscription->runMode()->value, $needle),
        ));

        if ($this->selected() !== null) {
            return;
        }

        $this->selectedId = isset($this->visible[0]) ? $this->visible[0]->id() : null;
        $this->offset = 0;
    }

    private function selectedIndex(): int
    {
        foreach ($this->visible as $index => $subscription) {
            if ($subscription->id() === $this->selectedId) {
                return $index;
            }
        }

        return 0;
    }

    /** @return list<string> */
    private function cells(Subscription $subscription): array
    {
        $position = $subscription->position();
        $lag = SubscriptionFormatter::lag($subscription, $this->head);
        $error = $subscription->subscriptionError()?->errorMessage;

        return [
            Theme::color(Theme::TEXT, $subscription->id()),
            Theme::color(Theme::MUTED, $subscription->group()),
            Theme::color(Theme::MUTED, SubscriptionFormatter::runMode($subscription)),
            $position === null || $this->head === null
                ? Theme::color(Theme::SUBTLE, '–')
                : Theme::progressBar($position, $this->head, self::PROGRESS_BAR_WIDTH)
                    . ' ' . Theme::fitRight(Theme::color(Theme::MUTED, Theme::percent($position, $this->head)), 4),
            $position === null ? Theme::color(Theme::SUBTLE, '–') : Theme::color(Theme::TEXT, Theme::number($position)),
            match (true) {
                $lag === null => Theme::color(Theme::SUBTLE, '–'),
                $lag === 0 => Theme::color(Theme::SUBTLE, '0'),
                default => Theme::color(Theme::WARNING, Theme::number($lag)),
            },
            Theme::status($subscription->status()),
            $subscription->retryAttempt() === 0
                ? Theme::color(Theme::SUBTLE, '0')
                : Theme::color(Theme::WARNING, (string)$subscription->retryAttempt()),
            Theme::color(Theme::MUTED, SubscriptionFormatter::age($subscription->lastSavedAt(), $this->now)),
            $error === null ? '' : Theme::color('38;5;174', $error),
        ];
    }

    /**
     * Calculates the column widths, dropping optional columns on small terminals.
     * A width of 0 hides the column.
     *
     * @param list<list<string>> $cells
     *
     * @return list<int>
     */
    private function widths(array $cells, int $columns): array
    {
        $widths = [];

        foreach (self::COLUMNS as $index => $name) {
            $width = AnsiUtils::visibleWidth($name);

            foreach ($cells as $row) {
                $width = max($width, AnsiUtils::visibleWidth($row[$index]));
            }

            $widths[] = $width;
        }

        $errorIndex = count($widths) - 1;
        $widths[$errorIndex] = 0;

        foreach (self::OPTIONAL as $optional) {
            if ($this->totalWidth($widths) <= $columns) {
                break;
            }

            $widths[(int)array_keys(self::COLUMNS, $optional, true)[0]] = 0;
        }

        $remaining = $columns - $this->totalWidth($widths) - 2;

        if ($remaining >= self::MIN_ERROR_WIDTH) {
            $widths[$errorIndex] = $remaining;
        }

        return $widths;
    }

    /** @param list<int> $widths */
    private function totalWidth(array $widths): int
    {
        $visible = array_filter($widths, static fn (int $width) => $width > 0);

        return array_sum($visible) + 2 * max(0, count($visible) - 1);
    }

    /**
     * @param list<string> $cells
     * @param list<int>    $widths
     */
    private function row(array $cells, array $widths): string
    {
        $parts = [];

        foreach ($cells as $index => $cell) {
            $width = $widths[$index] ?? 0;

            if ($width === 0) {
                continue;
            }

            $parts[] = in_array(self::COLUMNS[$index], self::RIGHT_ALIGNED, true)
                ? Theme::fitRight($cell, $width)
                : Theme::fit($cell, $width);
        }

        return implode(self::GAP, $parts);
    }
}
