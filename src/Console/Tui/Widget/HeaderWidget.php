<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\Widget;

use Patchlevel\EventSourcing\Console\Tui\Theme;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

use function array_map;
use function implode;

/**
 * Top bar with branding, breadcrumbs and meta information, followed by the status summary.
 *
 * @experimental
 */
final class HeaderWidget extends AbstractWidget
{
    /** @var list<string> */
    private array $crumbs = [];

    /** @var list<string> */
    private array $meta = [];

    private string $summary = '';

    /** @param list<string> $crumbs */
    public function setCrumbs(array $crumbs): void
    {
        $this->crumbs = $crumbs;
        $this->invalidate();
    }

    /** @param list<string> $meta */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
        $this->invalidate();
    }

    public function setSummary(string $summary): void
    {
        $this->summary = $summary;
        $this->invalidate();
    }

    /** @return list<string> */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();

        $crumbs = array_map(
            static fn (string $crumb) => Theme::color(Theme::TEXT, $crumb),
            $this->crumbs,
        );

        $left = ' ' . Theme::bold(Theme::ACCENT, '◆ event-sourcing')
            . ($crumbs === [] ? '' : Theme::color(Theme::SUBTLE, '  ›  ') . implode(Theme::color(Theme::SUBTLE, '  ›  '), $crumbs));

        $right = implode(Theme::color(Theme::SUBTLE, '  ·  '), $this->meta) . ' ';

        return [
            Theme::spread($left, $right, $columns),
            Theme::fit(' ' . $this->summary, $columns),
        ];
    }
}
