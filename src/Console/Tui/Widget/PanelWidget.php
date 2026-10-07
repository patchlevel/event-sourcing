<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\Widget;

use Patchlevel\EventSourcing\Console\Tui\Theme;
use Patchlevel\EventSourcing\Console\Tui\View\View;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

use function array_map;
use function count;
use function max;
use function min;

/**
 * Fills the remaining screen with the current view inside a frame,
 * optionally with a confirmation dialog on top.
 *
 * @experimental
 */
final class PanelWidget extends AbstractWidget implements VerticallyExpandableInterface
{
    /** @var list<string>|null */
    private array|null $dialog = null;
    private string $dialogTitle = '';
    private string $dialogColor = Theme::ACCENT;

    public function __construct(
        private View $view,
    ) {
    }

    public function view(): View
    {
        return $this->view;
    }

    public function show(View $view): void
    {
        $this->view = $view;
        $this->invalidate();
    }

    /** @param list<string> $lines */
    public function openDialog(string $title, array $lines, string $color = Theme::ACCENT): void
    {
        $this->dialogTitle = $title;
        $this->dialogColor = $color;
        $this->dialog = $lines;
        $this->invalidate();
    }

    public function closeDialog(): void
    {
        $this->dialog = null;
        $this->invalidate();
    }

    public function hasDialog(): bool
    {
        return $this->dialog !== null;
    }

    public function expandVertically(bool $expand): static
    {
        return $this;
    }

    public function isVerticallyExpanded(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $rows = max(3, $context->getRows());

        $content = $this->view->render(max(1, $columns - 4), $rows - 2);
        $lines = Theme::frame($this->view->title(), $content, $columns, $rows, $this->view->label());

        if ($this->dialog === null) {
            return $lines;
        }

        $width = 0;

        foreach ($this->dialog as $line) {
            $width = max($width, AnsiUtils::visibleWidth($line));
        }

        $width = min($columns - 4, max(44, $width + 8));
        $content = ['', ...array_map(static fn (string $line) => ' ' . $line, $this->dialog), ''];
        $box = Theme::frame(
            Theme::bold($this->dialogColor, $this->dialogTitle),
            $content,
            $width,
            count($content) + 2,
            '',
            $this->dialogColor,
        );

        return Theme::overlay($lines, $box, $columns);
    }
}
