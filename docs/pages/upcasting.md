# Upcasting

There are cases where the already have events in our stream but there is data missing or not in the right format for our
new usecase. Normally you would need to create versioned events for this. This can lead to many versions of the same
event which could lead to some chaos. To prevent this we offer `Upcaster`, which can operate on the payload before
denormalizing to an event object. There you can change the event name and adjust the payload of the event.

## Adjust payload

Let's assume we have an `ProfileCreated` event which holds an email. Now the business needs to have all emails to be in
lower case. For that we could adjust the aggregate and the projections to take care of that. Or we can do this
beforehand so we don't need to maintain two different places.

```php
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;

final class ProfileCreatedEmailLowerCastUpcaster implements Upcaster
{
    public function __invoke(Upcast $upcast): Upcast
    {
        // ignore if other event is processed
        if ($upcast->eventName !== 'profile_created') {
            return $upcast;
        }

        if (!array_key_exists('email', $upcast->payload) || !is_string($upcast->payload['email'])) {
            return $upcast;
        }

        return $upcast->replacePayloadByKey('email', strtolower($upcast->payload['email']));
    }
}
```
!!! warning

    You need to consider that other events are passed to the Upcaster. So and early out is here endorsed.
    
## Adjust event name

For the upgrade to 2.0.0 this feature is also really handy since we adjusted the event value from FQCN to an unique
name which the user needs to choose. This opens up for moving or renaming the events at code level. Here an example for
the upgrade path.

```php
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;

final class LegacyEventNameUpaster implements Upcaster
{
    /** @param array<string, string> $eventNameMap */
    public function __construct(
        private readonly array $eventNameMap,
    ) {
    }

    public function __invoke(Upcast $upcast): Upcast
    {
        if (array_key_exists($upcast->eventName, $this->eventNameMap)) {
            return $upcast->replaceEventName($this->eventNameMap[$upcast->eventName]);
        }

        return $upcast;
    }
}
```
## Use upcasting

After we have defined the upcasting rules, we also have to pass the whole thing to the serializer.
Since we have multiple upcasters, we use a chain here.

```php
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;

/** @var EventRegistry $eventRegistry */
$upcaster = new UpcasterChain([
    new ProfileCreatedEmailLowerCastUpcaster(),
    new LegacyEventNameUpaster(['old_event_name' => 'new_event_name']),
]);

$serializer = DefaultEventSerializer::createFromPaths(
    ['src/Domain'],
    $upcaster,
);
```
## Update event stream

But what if we need it also in our stream because some other applications has also access on it? Or want to cleanup our
Upcasters since we have collected alot of them over the time? Then we can use our pipeline feature without any
middlewares to achive a complete rebuild of our stream with adjusted event data.

```php
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'event-stream:cleanup',
    description: 'rebuild event stream',
)]
final class EventStreamCleanupCommand extends Command
{
    public function __construct(
        private readonly Store $sourceStore,
        private readonly Store $targetStore,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pipeline = new Pipeline(
            new StoreSource($this->sourceStore),
            new StoreTarget($this->targetStore),
        );

        $pipeline->run();

        return Command::SUCCESS;
    }
}
```
!!! danger

    Under no circumstances may the same store be used that is used for the source. 
    Otherwise the store will be broken afterwards!
    
!!! note

    You can find out more about the pipeline [here](pipeline.md).
    