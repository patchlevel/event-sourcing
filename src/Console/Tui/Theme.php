<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui;

use Patchlevel\EventSourcing\Subscription\Status;
use Symfony\Component\Tui\Ansi\AnsiUtils;

use function array_fill;
use function array_values;
use function count;
use function intdiv;
use function max;
use function min;
use function number_format;
use function round;
use function sprintf;
use function str_repeat;

/**
 * Colors and drawing helpers for the subscription dashboard.
 *
 * Uses the 256 color palette, which is supported by all common terminals.
 *
 * @experimental
 */
final class Theme
{
    public const TEXT = '38;5;252';
    public const MUTED = '38;5;245';
    public const SUBTLE = '38;5;240';
    public const BORDER = '38;5;238';
    public const ACCENT = '38;5;111';
    public const HIGHLIGHT = '38;5;176';
    public const SUCCESS = '38;5;114';
    public const WARNING = '38;5;221';
    public const DANGER = '38;5;203';

    public const SELECTED_BACKGROUND = '48;5;236';
    public const PILL_BACKGROUND = '48;5;237';

    public static function color(string $code, string $text): string
    {
        return "\e[" . $code . 'm' . $text . "\e[0m";
    }

    public static function bold(string $code, string $text): string
    {
        return self::color('1;' . $code, $text);
    }

    public static function statusColor(Status $status): string
    {
        return match ($status) {
            Status::New => '38;5;80',
            Status::Booting => self::WARNING,
            Status::Active => self::SUCCESS,
            Status::Paused => self::MUTED,
            Status::Finished => '38;5;75',
            Status::Detached => self::HIGHLIGHT,
            Status::Error => '38;5;215',
            Status::Failed => self::DANGER,
        };
    }

    public static function statusIcon(Status $status): string
    {
        return match ($status) {
            Status::New => '○',
            Status::Booting => '◐',
            Status::Active => '●',
            Status::Paused => '‖',
            Status::Finished => '✓',
            Status::Detached => '◌',
            Status::Error, Status::Failed => '✗',
        };
    }

    public static function status(Status $status): string
    {
        return self::color(self::statusColor($status), self::statusIcon($status) . ' ' . $status->value);
    }

    /** A key hint like in modern tuis: the key on a subtle background, followed by the label. */
    public static function keyHint(string $key, string $label): string
    {
        return self::color('1;' . self::TEXT . ';' . self::PILL_BACKGROUND, ' ' . $key . ' ')
            . ' ' . self::color(self::MUTED, $label);
    }

    public static function number(int $number): string
    {
        return number_format($number, 0, '.', ',');
    }

    /** Messages per second, with one decimal for small rates. */
    public static function rate(float $rate): string
    {
        return $rate < 10 ? sprintf('%.1f', $rate) : self::number((int)round($rate));
    }

    public static function progressBar(int $position, int $head, int $width): string
    {
        $ratio = $head <= 0 ? 1.0 : min(1.0, max(0.0, $position / $head));
        $filled = (int)round($ratio * $width);

        if ($filled === $width && $position < $head) {
            $filled = $width - 1;
        }

        $color = $ratio >= 1.0 ? self::SUCCESS : self::ACCENT;

        return self::color($color, str_repeat('━', $filled))
            . self::color(self::BORDER, str_repeat('━', $width - $filled));
    }

    public static function percent(int $position, int $head): string
    {
        if ($head <= 0 || $position >= $head) {
            return '100%';
        }

        return sprintf('%d%%', (int)($position / $head * 100));
    }

    /** Truncates (with ellipsis) or pads the text to exactly the given width. */
    public static function fit(string $text, int $width): string
    {
        if ($width <= 0) {
            return '';
        }

        return AnsiUtils::truncateToWidth($text, $width, '…', true);
    }

    public static function fitRight(string $text, int $width): string
    {
        if ($width <= 0) {
            return '';
        }

        $visible = AnsiUtils::visibleWidth($text);

        if ($visible >= $width) {
            return self::fit($text, $width);
        }

        return str_repeat(' ', $width - $visible) . $text;
    }

    /** Places the right text at the end of the line, truncating the left text if needed. */
    public static function spread(string $left, string $right, int $width): string
    {
        $rightWidth = AnsiUtils::visibleWidth($right);

        if ($rightWidth >= $width) {
            return self::fit($right, $width);
        }

        return self::fit($left, $width - $rightWidth) . $right;
    }

    /**
     * Draws a box with rounded corners, a title on the left and an optional label on the right.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function frame(
        string $title,
        array $lines,
        int $columns,
        int $rows,
        string $label = '',
        string $borderColor = self::BORDER,
    ): array {
        if ($rows <= 0 || $columns <= 0) {
            return [];
        }

        if ($columns < 8 || $rows < 2) {
            return array_fill(0, $rows, str_repeat(' ', $columns));
        }

        $innerWidth = $columns - 4;
        $innerRows = $rows - 2;

        $title = ' ' . AnsiUtils::truncateToWidth($title, max(0, $columns - 8), '…') . "\e[0m ";
        $label = $label === '' ? '' : ' ' . $label . "\e[0m ";

        if (AnsiUtils::visibleWidth($title) + AnsiUtils::visibleWidth($label) + 5 > $columns) {
            $label = '';
        }

        $fill = max(0, $columns - 4 - AnsiUtils::visibleWidth($title) - AnsiUtils::visibleWidth($label));

        $result = [
            self::color($borderColor, '╭─')
            . $title
            . self::color($borderColor, str_repeat('─', $fill))
            . $label
            . self::color($borderColor, '─╮'),
        ];

        $side = self::color($borderColor, '│');

        for ($i = 0; $i < $innerRows; $i++) {
            $line = $lines[$i] ?? '';
            $result[] = $side . ' ' . self::fit($line, $innerWidth) . "\e[0m " . $side;
        }

        $result[] = self::color($borderColor, '╰' . str_repeat('─', $columns - 2) . '╯');

        return $result;
    }

    /**
     * Places the box lines centered on top of the given background lines.
     *
     * @param list<string> $background
     * @param list<string> $box
     *
     * @return list<string>
     */
    public static function overlay(array $background, array $box, int $columns): array
    {
        $boxRows = count($box);
        $boxWidth = $boxRows > 0 ? AnsiUtils::visibleWidth($box[0]) : 0;

        if ($boxRows === 0 || $boxRows > count($background) || $boxWidth > $columns) {
            return $background;
        }

        $top = intdiv(count($background) - $boxRows, 2);
        $left = intdiv($columns - $boxWidth, 2);

        foreach ($box as $i => $boxLine) {
            $line = self::fit($background[$top + $i], $columns);

            $composed = AnsiUtils::sliceByColumn($line, 0, $left, true)
                . "\e[0m" . $boxLine . "\e[0m"
                . AnsiUtils::sliceByColumn($line, $left + $boxWidth, $columns - $left - $boxWidth, true);

            // never exceed the available width, the renderer rejects such lines
            $background[$top + $i] = AnsiUtils::truncateToWidth($composed, $columns, '');
        }

        return array_values($background);
    }
}
