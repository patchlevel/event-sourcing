<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Tui;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function in_array;

/** @experimental */
enum SubscriptionAction: string
{
    case Setup = 'setup';
    case Boot = 'boot';
    case Run = 'run';
    case Pause = 'pause';
    case Reactivate = 'reactivate';
    case Refresh = 'refresh';
    case Teardown = 'teardown';
    case Remove = 'remove';
    case Rebuild = 'rebuild';

    /** The key as understood by the tui keybindings. */
    public function key(): string
    {
        return match ($this) {
            self::Setup => 's',
            self::Boot => 'b',
            self::Run => 'r',
            self::Pause => 'p',
            self::Reactivate => 'a',
            self::Refresh => 'f',
            self::Teardown => 't',
            self::Remove => 'ctrl+d',
            self::Rebuild => 'shift+r',
        };
    }

    /** The key as shown to the user. */
    public function keyLabel(): string
    {
        return match ($this) {
            self::Remove => 'ctrl-d',
            self::Rebuild => 'shift-r',
            default => $this->key(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Setup',
            self::Boot => 'Boot',
            self::Run => 'Run',
            self::Pause => 'Pause',
            self::Reactivate => 'Reactivate',
            self::Refresh => 'Refresh',
            self::Teardown => 'Teardown',
            self::Remove => 'Remove',
            self::Rebuild => 'Rebuild',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Setup => 'Set up new subscriptions',
            self::Boot => 'Boot subscriptions that are in booting state',
            self::Run => 'Process new messages for active subscriptions',
            self::Pause => 'Pause active, booting or failing subscriptions',
            self::Reactivate => 'Reactivate paused, finished, detached or failing subscriptions',
            self::Refresh => 'Sync group, run mode and cleanup tasks from the subscriber',
            self::Teardown => 'Tear down detached subscriptions',
            self::Remove => 'Remove subscriptions and call their teardown',
            self::Rebuild => 'Remove and boot subscriptions again',
        };
    }

    public function needsConfirmation(): bool
    {
        return in_array($this, [self::Teardown, self::Remove, self::Rebuild], true);
    }

    /**
     * Mirrors the statuses the engine handlers act on,
     * so that only actions that will actually do something are offered.
     */
    public function supports(Subscription $subscription): bool
    {
        $statuses = match ($this) {
            self::Setup => [Status::New],
            self::Boot => [Status::Booting],
            self::Run => [Status::Active],
            self::Pause => [Status::Active, Status::Booting, Status::Error],
            self::Reactivate => [Status::Error, Status::Failed, Status::Detached, Status::Paused, Status::Finished],
            self::Teardown => [Status::Detached],
            self::Refresh, self::Remove, self::Rebuild => null,
        };

        return $statuses === null || in_array($subscription->status(), $statuses, true);
    }

    /**
     * @param list<string>      $ids
     * @param positive-int|null $messageLimit
     *
     * @return list<Command>
     */
    public function commands(array $ids, int|null $messageLimit = null): array
    {
        return match ($this) {
            self::Setup => [new Setup($ids)],
            self::Boot => [new Boot($ids, null, $messageLimit)],
            self::Run => [new Run($ids, null, $messageLimit)],
            self::Pause => [new Pause($ids)],
            self::Reactivate => [new Reactivate($ids)],
            self::Refresh => [new Refresh($ids)],
            self::Teardown => [new Teardown($ids)],
            self::Remove => [new Remove($ids)],
            self::Rebuild => [new Remove($ids), new Boot($ids, null, $messageLimit)],
        };
    }
}
