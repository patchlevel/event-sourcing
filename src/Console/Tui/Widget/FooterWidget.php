<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui\Widget;

use Patchlevel\EventSourcing\Console\Tui\Theme;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

use function max;

/**
 * Bottom lines with the key hints (or the filter prompt) and the flash message.
 *
 * @experimental
 */
final class FooterWidget extends AbstractWidget
{
    /** @var list<array{string, string}> */
    private array $hints = [];
    private string|null $prompt = null;
    private string $message = '';
    private string $messageColor = Theme::MUTED;

    /** @param list<array{string, string}> $hints list of [key, label] */
    public function setHints(array $hints): void
    {
        $this->hints = $hints;
        $this->invalidate();
    }

    public function setPrompt(string|null $prompt): void
    {
        $this->prompt = $prompt;
        $this->invalidate();
    }

    public function setMessage(string $message, string $color = Theme::MUTED): void
    {
        $this->message = $message;
        $this->messageColor = $color;
        $this->invalidate();
    }

    /** @return list<string> */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();

        $right = '';

        if ($this->message !== '') {
            $icon = match ($this->messageColor) {
                Theme::SUCCESS => '✓',
                Theme::WARNING => '!',
                Theme::DANGER => '✗',
                default => '',
            };

            $right = Theme::color($this->messageColor, ($icon === '' ? '' : $icon . ' ') . $this->message) . ' ';
        }

        if ($this->prompt !== null) {
            $prompt = ' ' . Theme::bold(Theme::HIGHLIGHT, '/') . ' '
                . Theme::color(Theme::TEXT, $this->prompt)
                . Theme::color(Theme::HIGHLIGHT, '▏');

            return [
                Theme::fit($prompt, $columns),
                Theme::spread(' ' . Theme::keyHint('enter', 'apply') . '  ' . Theme::keyHint('esc', 'clear'), $right, $columns),
            ];
        }

        $lines = ['', ''];
        $line = 0;

        foreach ($this->hints as [$key, $label]) {
            $hint = ' ' . Theme::keyHint($key, $label) . ' ';
            $available = $line === 1 ? $columns - AnsiUtils::visibleWidth($right) - 1 : $columns;

            if (AnsiUtils::visibleWidth($lines[$line] . $hint) > max(0, $available)) {
                // hints that do not fit are left out instead of being cut in half
                if ($line === 1) {
                    break;
                }

                $line = 1;
            }

            $lines[$line] .= $hint;
        }

        return [
            Theme::fit($lines[0], $columns),
            Theme::spread($lines[1], $right, $columns),
        ];
    }
}
