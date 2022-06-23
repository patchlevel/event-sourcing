# Upcasting

There are cases where the already have events in our stream but there is data missing or not in the right format for our
new usecase. Normally you would need to create versioned events for this. This can lead to many versions of the same
event which could lead to some chaos. To prevent this we offer `Upcaster`, which can operate on the payload before
denormalizing to an event object. There you can change the event name and adjust the payload of the event.

## Adjust payload

Let's assume the have an `ProfileCreated` event which holds an email. Now the business needs to have all emails to be in
lower cast. For that we could adjust the aggregate and the projections to take care of that. Or we can do this
beforehand so we dont need to maintain two different places.

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
        
        $payload = $upcast->payload;
        $payload['email'] = strtolower($payload['email']);
        
         return new Upcast($upcast->eventName, $payload)
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
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;

final class LegacyEventNameUpaster implements Upcaster
{
    public function __construct(private readonly EventRegistry $eventRegistry){}
    
    public function __invoke(Upcast $upcast): Upcast
    {
        return new Upcast($this->eventRegistry->eventName($upcast->eventName), $upcast->payload);
    }
}
```

## Update event stream

But what if we need it also in our stream because some other applications has also access on it? Or want to cleanup our
Upcasters since we have collected alot of them over the time? Then we can use our pipeline feature without any
middlewares to achive a complete rebuild of our stream with adjusted event data.

```php
final class EventStreamCleanupCommand extends Command
{
    protected static $defaultName = 'event-stream:cleanup';
    protected static $defaultDescription = 'rebuild event stream';

    public function __construct(
        private readonly Store $sourceStore, 
        private readonly Store $targetStore, 
        private readonly ProjectionHandler $projectionHandler
    ){
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pipeline = new Pipeline(new StoreSource($sourceStore), new StoreTarget($targetStore));
        $pipeline->run();
    }
```

!!! danger

    Under no circumstances may the same store be used that is used for the source. 
    Otherwise the store will be broken afterwards!

!!! note

    You can find out more about the pipeline [here](pipeline.md).