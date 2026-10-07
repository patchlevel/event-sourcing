<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\View;

/** @experimental */
interface View
{
    public function title(): string;

    /**
     * Shown on the right side of the frame.
     * Called after render(), so it can reflect the rendered state (e.g. scroll position).
     */
    public function label(): string;

    /** @return list<string> */
    public function render(int $columns, int $rows): array;

    public function moveBy(int $delta): void;

    public function pageUp(): void;

    public function pageDown(): void;

    public function moveToStart(): void;

    public function moveToEnd(): void;
}
