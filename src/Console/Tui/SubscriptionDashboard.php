<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Console\Tui\View\SubscriptionTableView;
use Patchlevel\EventSourcing\Console\Tui\View\TextView;
use Patchlevel\EventSourcing\Console\Tui\Widget\FooterWidget;
use Patchlevel\EventSourcing\Console\Tui\Widget\HeaderWidget;
use Patchlevel\EventSourcing\Console\Tui\Widget\PanelWidget;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Clock\ClockInterface;
use Revolt\EventLoop;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Terminal\Terminal;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function max;
use function mb_substr;
use function ord;
use function sprintf;
use function str_repeat;
use function strlen;
use function strtolower;

/**
 * A k9s like full screen ui to monitor and manage subscriptions.
 *
 * @experimental
 */
final class SubscriptionDashboard
{
    private const FLASH_SECONDS = 5;

    private readonly Tui $tui;
    private readonly Keybindings $keybindings;

    private readonly HeaderWidget $header;
    private readonly PanelWidget $panel;
    private readonly FooterWidget $footer;

    private readonly SubscriptionTableView $table;
    private readonly TextView $describe;
    private readonly TextView $help;

    private string|null $describedId = null;
    private bool $filterEditing = false;
    private SubscriptionAction|null $pendingAction = null;

    /** @var list<Subscription> */
    private array $pendingTargets = [];

    private int|null $head = null;
    private DateTimeImmutable|null $lastReload = null;
    private int $flashUntil = 0;

    /** @param positive-int|null $messageLimit */
    public function __construct(
        private readonly SubscriptionEngine $engine,
        private readonly Store|null $store = null,
        private readonly SubscriptionEngineCriteria|null $criteria = null,
        private readonly float $refreshInterval = 2.0,
        private readonly int|null $messageLimit = null,
        TerminalInterface|null $terminal = null,
        private readonly ClockInterface|null $clock = null,
    ) {
        $this->keybindings = self::keybindings();
        $this->tui = new Tui(self::styleSheet(), $terminal ?? new Terminal(), $this->keybindings);

        $this->table = new SubscriptionTableView();
        $this->describe = new TextView('Describe');
        $this->help = new TextView('Help', $this->helpLines());

        $this->header = new HeaderWidget();
        $this->panel = new PanelWidget($this->table);
        $this->footer = new FooterWidget();

        $this->tui->add($this->header);
        $this->tui->add($this->panel);
        $this->tui->add($this->footer);

        $this->tui->addListener($this->onInput(...));

        $this->reload();
    }

    public function run(): void
    {
        $terminal = $this->tui->getTerminal();
        $terminal->write("\x1b[?1049h\x1b[2J\x1b[H");

        $timer = EventLoop::repeat($this->refreshInterval, function (): void {
            $this->reload();
        });

        try {
            $this->tui->requestRender(true);
            $this->tui->run();
        } finally {
            EventLoop::cancel($timer);
            $terminal->write("\x1b[?1049l");
        }
    }

    public function reload(): void
    {
        try {
            $subscriptions = $this->engine->subscriptions($this->criteria);
            $this->head = $this->head();
        } catch (Throwable $e) {
            $this->flash(sprintf('Failed to load subscriptions: %s', $e->getMessage()), Theme::DANGER);
            $this->tui->requestRender();

            return;
        }

        $this->lastReload = $this->now();
        $this->table->update($subscriptions, $this->head, $this->lastReload);

        if ($this->flashUntil > 0 && $this->flashUntil < $this->lastReload->getTimestamp()) {
            $this->flashUntil = 0;
            $this->footer->setMessage('');
        }

        if ($this->describedId !== null) {
            $this->updateDescribe();
        }

        $this->updateChrome();
    }

    private function onInput(InputEvent $event): void
    {
        $event->stopPropagation();
        $data = $event->getData();

        if ($this->matches($data, 'force_quit')) {
            $this->tui->stop();

            return;
        }

        if ($this->panel->hasDialog()) {
            $this->handleDialogInput($data);
        } elseif ($this->filterEditing) {
            $this->handleFilterInput($data);
        } else {
            $this->handleInput($data);
        }

        $this->updateChrome();
    }

    private function handleDialogInput(string $data): void
    {
        if ($this->matches($data, 'confirm')) {
            $action = $this->pendingAction;
            $targets = $this->pendingTargets;
            $this->closeDialog();

            if ($action !== null) {
                $this->execute($action, $targets);
            }

            return;
        }

        if (!$this->matches($data, 'cancel')) {
            return;
        }

        $this->closeDialog();
        $this->flash('Cancelled');
    }

    private function handleFilterInput(string $data): void
    {
        if ($this->matches($data, 'filter_apply')) {
            $this->filterEditing = false;

            return;
        }

        if ($this->matches($data, 'back')) {
            $this->filterEditing = false;
            $this->table->changeFilter('');

            return;
        }

        if ($this->matches($data, 'filter_backspace')) {
            $filter = $this->table->filter();
            $this->table->changeFilter(mb_substr($filter, 0, -1));

            return;
        }

        if ($this->hasControlCharacters($data)) {
            return;
        }

        $this->table->changeFilter($this->table->filter() . $data);
    }

    private function handleInput(string $data): void
    {
        $view = $this->panel->view();

        if ($this->matches($data, 'quit')) {
            $this->tui->stop();

            return;
        }

        if ($this->matches($data, 'back')) {
            $this->back();

            return;
        }

        if ($this->matches($data, 'up')) {
            $view->moveBy(-1);
        } elseif ($this->matches($data, 'down')) {
            $view->moveBy(1);
        } elseif ($this->matches($data, 'page_up')) {
            $view->pageUp();
        } elseif ($this->matches($data, 'page_down')) {
            $view->pageDown();
        } elseif ($this->matches($data, 'start')) {
            $view->moveToStart();
        } elseif ($this->matches($data, 'end')) {
            $view->moveToEnd();
        } elseif ($this->matches($data, 'help')) {
            $this->describedId = null;
            $this->panel->show($this->help);
        } elseif ($this->matches($data, 'reload')) {
            $this->reload();
            $this->flash('Reloaded');
        } elseif ($view === $this->table) {
            $this->handleTableInput($data);
        }

        foreach (SubscriptionAction::cases() as $action) {
            if (!$this->matches($data, 'action_' . $action->value)) {
                continue;
            }

            $this->trigger($action);

            return;
        }

        $this->panel->invalidate();
    }

    private function handleTableInput(string $data): void
    {
        if ($this->matches($data, 'filter')) {
            $this->filterEditing = true;
        } elseif ($this->matches($data, 'mark')) {
            $this->table->toggleMark();
        } elseif ($this->matches($data, 'describe')) {
            $selected = $this->table->selected();

            if ($selected === null) {
                return;
            }

            $this->describedId = $selected->id();
            $this->updateDescribe();
            $this->describe->moveToStart();
            $this->panel->show($this->describe);
        }
    }

    private function back(): void
    {
        if ($this->panel->view() !== $this->table) {
            $this->describedId = null;
            $this->panel->show($this->table);

            return;
        }

        if ($this->table->hasMarks()) {
            $this->table->clearMarks();

            return;
        }

        $this->table->changeFilter('');
    }

    private function trigger(SubscriptionAction $action): void
    {
        $targets = $this->targets();

        if ($targets === []) {
            $this->flash('No subscription selected', Theme::WARNING);

            return;
        }

        $supported = array_values(array_filter($targets, $action->supports(...)));

        if ($supported === []) {
            $this->flash(
                count($targets) === 1
                    ? sprintf('%s is not possible for subscription "%s" with status "%s"', $action->label(), $targets[0]->id(), $targets[0]->status()->value)
                    : sprintf('%s is not possible for any of the marked subscriptions', $action->label()),
                Theme::WARNING,
            );

            return;
        }

        if (!$action->needsConfirmation()) {
            $this->execute($action, $supported);

            return;
        }

        $this->pendingAction = $action;
        $this->pendingTargets = $supported;

        $this->panel->openDialog(
            $action->label(),
            [
                Theme::color(Theme::TEXT, sprintf('%s %s?', $action->label(), $this->describeTargets($supported))),
                Theme::color(Theme::MUTED, $action->description()),
                '',
                Theme::color('1;38;5;255;48;5;167', sprintf(' y  %s ', $action->label()))
                . '   ' . Theme::color(Theme::TEXT . ';' . Theme::PILL_BACKGROUND, ' n  Cancel '),
            ],
            Theme::DANGER,
        );
    }

    /** @param list<Subscription> $targets */
    private function execute(SubscriptionAction $action, array $targets): void
    {
        $ids = array_map(static fn (Subscription $subscription) => $subscription->id(), $targets);

        // show the progress before the (blocking) command is executed
        $this->flash(sprintf('%s %s...', $action->label(), $this->describeTargets($targets)), Theme::WARNING);
        $this->tui->requestRender();
        $this->tui->processRender();

        $errors = [];

        try {
            foreach ($action->commands($ids, $this->messageLimit) as $command) {
                foreach ($this->engine->execute($command)->errors as $error) {
                    $errors[] = sprintf('%s: %s', $error->subscriptionId, $error->message);
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        $this->table->clearMarks();
        $this->reload();

        if ($errors === []) {
            $this->flash(sprintf('%s %s done', $action->label(), $this->describeTargets($targets)), Theme::SUCCESS);

            return;
        }

        $this->flash(
            sprintf('%s failed: %s', $action->label(), $errors[0]) . (count($errors) > 1 ? sprintf(' (+%d more)', count($errors) - 1) : ''),
            Theme::DANGER,
        );
    }

    /** @return list<Subscription> */
    private function targets(): array
    {
        if ($this->panel->view() === $this->table) {
            return $this->table->targets();
        }

        $described = $this->described();

        return $described === null ? [] : [$described];
    }

    private function described(): Subscription|null
    {
        foreach ($this->table->subscriptions() as $subscription) {
            if ($subscription->id() === $this->describedId) {
                return $subscription;
            }
        }

        return null;
    }

    private function updateDescribe(): void
    {
        $subscription = $this->described();

        if ($subscription === null) {
            $this->flash(sprintf('Subscription "%s" no longer exists', (string)$this->describedId), Theme::WARNING);
            $this->describedId = null;
            $this->panel->show($this->table);

            return;
        }

        $this->describe->update(
            sprintf('Describe(%s)', $subscription->id()),
            SubscriptionFormatter::describe($subscription, $this->head, $this->lastReload ?? $this->now()),
        );
    }

    private function closeDialog(): void
    {
        $this->pendingAction = null;
        $this->pendingTargets = [];
        $this->panel->closeDialog();
    }

    private function updateChrome(): void
    {
        $view = $this->panel->view();

        $crumbs = ['subscriptions'];

        if ($view === $this->describe && $this->describedId !== null) {
            $crumbs[] = $this->describedId;
        } elseif ($view === $this->help) {
            $crumbs[] = 'help';
        }

        $meta = [];

        if ($this->scope() !== null) {
            $meta[] = Theme::color(Theme::HIGHLIGHT, (string)$this->scope());
        }

        $meta[] = Theme::color(Theme::MUTED, 'head ') . Theme::color(Theme::TEXT, $this->head === null ? '–' : Theme::number($this->head));
        $meta[] = Theme::color(Theme::MUTED, sprintf('⟳ %ss', $this->refreshInterval));
        $meta[] = Theme::color(Theme::MUTED, $this->lastReload?->format('H:i:s') ?? '–');

        $this->header->setCrumbs($crumbs);
        $this->header->setMeta($meta);
        $this->header->setSummary($this->summary($this->table->subscriptions()));

        $this->footer->setHints($this->hints());
        $this->footer->setPrompt($this->filterEditing ? $this->table->filter() : null);
        $this->panel->invalidate();
        $this->tui->requestRender();
    }

    /** @return list<array{string, string}> */
    private function hints(): array
    {
        if ($this->panel->hasDialog()) {
            return [['y', 'Confirm'], ['n', 'Cancel']];
        }

        if ($this->filterEditing) {
            return [['enter', 'Apply filter'], ['esc', 'Clear filter']];
        }

        $hints = [];
        $targets = $this->targets();

        foreach (SubscriptionAction::cases() as $action) {
            foreach ($targets as $target) {
                if ($action->supports($target)) {
                    $hints[] = [$action->keyLabel(), strtolower($action->label())];

                    break;
                }
            }
        }

        if ($this->panel->view() === $this->table) {
            $hints[] = ['enter', 'describe'];
            $hints[] = ['/', 'filter'];
            $hints[] = ['space', 'mark'];
        } else {
            $hints[] = ['esc', 'back'];
        }

        $hints[] = ['?', 'help'];
        $hints[] = ['q', 'quit'];

        return $hints;
    }

    /** @param list<Subscription> $subscriptions */
    private function summary(array $subscriptions): string
    {
        $counts = [];

        foreach ($subscriptions as $subscription) {
            $counts[$subscription->status()->value] = ($counts[$subscription->status()->value] ?? 0) + 1;
        }

        $parts = [Theme::bold(Theme::TEXT, (string)count($subscriptions)) . Theme::color(Theme::MUTED, ' subscriptions')];

        foreach (Status::cases() as $status) {
            if (!isset($counts[$status->value])) {
                continue;
            }

            $parts[] = Theme::color(
                Theme::statusColor($status),
                sprintf('%s %d %s', Theme::statusIcon($status), $counts[$status->value], $status->value),
            );
        }

        return implode('   ', $parts);
    }

    private function scope(): string|null
    {
        $parts = [];

        if ($this->criteria?->ids !== null) {
            $parts[] = 'id=' . implode(',', $this->criteria->ids);
        }

        if ($this->criteria?->groups !== null) {
            $parts[] = 'group=' . implode(',', $this->criteria->groups);
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /** @param list<Subscription> $targets */
    private function describeTargets(array $targets): string
    {
        if (count($targets) === 1) {
            return sprintf('"%s"', $targets[0]->id());
        }

        return sprintf('%d subscriptions', count($targets));
    }

    private function flash(string $message, string $color = Theme::MUTED): void
    {
        $this->flashUntil = $this->now()->getTimestamp() + self::FLASH_SECONDS;
        $this->footer->setMessage($message, $color);
    }

    private function head(): int|null
    {
        if ($this->store === null) {
            return null;
        }

        $stream = $this->store->load(null, 1, null, true);

        try {
            $message = $stream->current();

            if ($message === null) {
                return 0;
            }

            if ($message->hasHeader(IndexHeader::class)) {
                return $message->header(IndexHeader::class)->index;
            }

            return $stream->index() ?? 0;
        } finally {
            $stream->close();
        }
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?? new DateTimeImmutable();
    }

    private function matches(string $data, string $action): bool
    {
        return $this->keybindings->matches($data, $action);
    }

    private function hasControlCharacters(string $data): bool
    {
        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $code = ord($data[$i]);

            if ($code < 32 || $code === 0x7F) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function helpLines(): array
    {
        $line = static fn (string $key, string $description) => '  ' . Theme::keyHint($key, '')
            . str_repeat(' ', max(1, 12 - AnsiUtils::visibleWidth($key))) . Theme::color(Theme::TEXT, $description);

        $lines = [Theme::bold(Theme::ACCENT, 'Actions'), ''];

        foreach (SubscriptionAction::cases() as $action) {
            $lines[] = $line($action->keyLabel(), $action->description());
        }

        return [
            ...$lines,
            '',
            Theme::color(Theme::MUTED, '  Actions apply to the marked subscriptions, or to the selected one if nothing is marked.'),
            '',
            Theme::bold(Theme::ACCENT, 'Navigation'),
            '',
            $line('↑ ↓  j k', 'Move the selection'),
            $line('pgup pgdn', 'Move one page'),
            $line('g G', 'Jump to the first or last entry'),
            $line('enter d', 'Describe the selected subscription'),
            $line('space', 'Mark or unmark the selected subscription'),
            $line('/', 'Filter by id, group, status or run mode'),
            $line('esc', 'Go back, clear marks or clear the filter'),
            $line('ctrl-r', 'Reload now'),
            $line('?', 'Show this help'),
            $line('q', 'Quit'),
        ];
    }

    private static function keybindings(): Keybindings
    {
        $bindings = [
            'quit' => ['q'],
            'force_quit' => ['ctrl+c'],
            'back' => [Key::ESCAPE],
            'up' => [Key::UP, 'k'],
            'down' => [Key::DOWN, 'j'],
            'page_up' => [Key::PAGE_UP],
            'page_down' => [Key::PAGE_DOWN],
            'start' => [Key::HOME, 'g'],
            'end' => [Key::END, 'shift+g'],
            'describe' => [Key::ENTER, 'd'],
            'filter' => ['/'],
            'filter_apply' => [Key::ENTER],
            'filter_backspace' => [Key::BACKSPACE],
            'mark' => [Key::SPACE],
            'reload' => ['ctrl+r'],
            'help' => ['?'],
            'confirm' => ['y', Key::ENTER],
            'cancel' => ['n', Key::ESCAPE],
        ];

        foreach (SubscriptionAction::cases() as $action) {
            $bindings['action_' . $action->value] = [$action->key()];
        }

        return new Keybindings($bindings);
    }

    private static function styleSheet(): StyleSheet
    {
        return new StyleSheet([
            ':root' => new Style(direction: Direction::Vertical, gap: 0, verticalAlign: VerticalAlign::Top),
        ]);
    }
}
