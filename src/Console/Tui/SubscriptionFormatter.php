<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;

use function ceil;
use function count;
use function implode;
use function intdiv;
use function is_array;
use function is_scalar;
use function max;
use function sprintf;
use function str_pad;
use function str_replace;
use function strrpos;
use function substr;

/**
 * @phpstan-import-type Context from SubscriptionError
 * @phpstan-import-type Trace from SubscriptionError
 * @experimental
 */
final class SubscriptionFormatter
{
    public static function lag(Subscription $subscription, int|null $head): int|null
    {
        $position = $subscription->position();

        if ($head === null || $position === null) {
            return null;
        }

        return max(0, $head - $position);
    }

    public static function age(DateTimeImmutable|null $time, DateTimeImmutable $now): string
    {
        if ($time === null) {
            return '-';
        }

        return self::duration(max(0, $now->getTimestamp() - $time->getTimestamp()));
    }

    public static function duration(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => $seconds . 's',
            $seconds < 3600 => intdiv($seconds, 60) . 'm',
            $seconds < 86400 => intdiv($seconds, 3600) . 'h',
            default => intdiv($seconds, 86400) . 'd',
        };
    }

    public static function runMode(Subscription $subscription): string
    {
        return str_replace('_', ' ', $subscription->runMode()->value);
    }

    /** @return list<string> */
    public static function describe(Subscription $subscription, int|null $head, DateTimeImmutable $now, float|null $rate = null): array
    {
        $error = $subscription->subscriptionError();
        $status = Theme::status($subscription->status());

        if ($error !== null) {
            $status .= Theme::color(Theme::MUTED, sprintf('  was %s', $error->previousStatus->value));
        }

        $lastSavedAt = $subscription->lastSavedAt();
        $cleanupTasks = $subscription->cleanupTasks();

        $lines = [
            ...self::section('Overview'),
            self::field('ID', Theme::bold(Theme::TEXT, $subscription->id())),
            self::field('Group', $subscription->group()),
            self::field('Run mode', self::runMode($subscription)),
            self::field('Status', $status),
            self::field('Progress', self::progress($subscription, $head)),
            self::field('Throughput', self::throughput($subscription, $head, $rate)),
            self::field('Retries', $subscription->retryAttempt() === 0
                ? Theme::color(Theme::SUBTLE, 'none')
                : Theme::color(Theme::WARNING, (string)$subscription->retryAttempt())),
            self::field('Last saved', $lastSavedAt === null
                ? Theme::color(Theme::SUBTLE, '–')
                : $lastSavedAt->format('Y-m-d H:i:s') . Theme::color(Theme::MUTED, sprintf('  %s ago', self::age($lastSavedAt, $now)))),
            self::field('Cleanup tasks', $cleanupTasks === null ? Theme::color(Theme::SUBTLE, 'none') : (string)count($cleanupTasks)),
        ];

        if ($error === null) {
            return $lines;
        }

        $lines[] = '';
        $lines = [...$lines, ...self::section('Error', Theme::DANGER)];

        if ($error->errorContext === null || $error->errorContext === []) {
            $lines[] = Theme::color(Theme::DANGER, '▎ ') . Theme::color(Theme::TEXT, $error->errorMessage);

            return $lines;
        }

        foreach ($error->errorContext as $context) {
            foreach (self::context($context) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private static function progress(Subscription $subscription, int|null $head): string
    {
        $position = $subscription->position();

        if ($position === null) {
            return Theme::color(Theme::SUBTLE, 'not started');
        }

        if ($head === null) {
            return Theme::number($position);
        }

        $lag = self::lag($subscription, $head) ?? 0;

        return Theme::progressBar($position, $head, 24)
            . '  ' . Theme::color(Theme::TEXT, Theme::percent($position, $head))
            . Theme::color(Theme::MUTED, sprintf('  %s / %s', Theme::number($position), Theme::number($head)))
            . ($lag > 0 ? Theme::color(Theme::WARNING, sprintf('  lag %s', Theme::number($lag))) : '');
    }

    private static function throughput(Subscription $subscription, int|null $head, float|null $rate): string
    {
        if ($rate === null) {
            return Theme::color(Theme::SUBTLE, '–');
        }

        $lag = self::lag($subscription, $head) ?? 0;

        return Theme::color(Theme::TEXT, Theme::rate($rate)) . Theme::color(Theme::MUTED, ' msg/s')
            . ($lag > 0 && $rate >= 0.05
                ? Theme::color(Theme::MUTED, sprintf('  ~%s left', self::duration((int)ceil($lag / $rate))))
                : '');
    }

    /** @return list<string> */
    private static function section(string $title, string $color = Theme::ACCENT): array
    {
        return [
            Theme::bold($color, $title),
            '',
        ];
    }

    /**
     * @param Context $context
     *
     * @return list<string>
     */
    private static function context(array $context): array
    {
        $bar = Theme::color(Theme::DANGER, '▎ ');

        $lines = [
            $bar . Theme::bold(Theme::TEXT, $context['class']),
            $bar . Theme::color(Theme::TEXT, $context['message']),
            $bar . Theme::color(Theme::MUTED, sprintf('%s:%d', $context['file'], $context['line'])),
            '',
            Theme::color(Theme::MUTED, 'Stack trace'),
            '',
        ];

        foreach ($context['trace'] as $index => $trace) {
            $lines[] = Theme::color(Theme::SUBTLE, sprintf('%3d  ', $index)) . Theme::color(Theme::TEXT, self::call($trace));

            if (!isset($trace['file'])) {
                continue;
            }

            $lines[] = '     ' . Theme::color(Theme::SUBTLE, sprintf('%s:%s', $trace['file'], $trace['line'] ?? '?'));
        }

        $lines[] = '';

        return $lines;
    }

    /** @param Trace $trace */
    private static function call(array $trace): string
    {
        $function = $trace['function'] ?? '{main}';

        if (isset($trace['class'])) {
            $function = self::shortClass($trace['class']) . ($trace['type'] ?? '::') . $function;
        }

        $args = [];

        foreach ($trace['args'] ?? [] as $arg) {
            $args[] = self::argument($arg);
        }

        return $function . '(' . implode(', ', $args) . ')';
    }

    private static function argument(mixed $arg): string
    {
        if (!is_array($arg) || !isset($arg[0]) || !is_scalar($arg[0])) {
            return '?';
        }

        $type = (string)$arg[0];
        $value = isset($arg[1]) && is_scalar($arg[1]) ? (string)$arg[1] : '';

        return match ($type) {
            'object' => self::shortClass($value),
            'string' => '"' . $value . '"',
            'integer', 'float' => $value,
            'boolean' => $value ? 'true' : 'false',
            default => $type,
        };
    }

    private static function shortClass(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    private static function field(string $label, string $value): string
    {
        return Theme::color(Theme::MUTED, str_pad($label, 16)) . Theme::color(Theme::TEXT, $value);
    }
}
