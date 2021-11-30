[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing/master)
[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing/v)](//packagist.org/packages/patchlevel/event-sourcing)
[![License](https://poser.pugx.org/patchlevel/event-sourcing/license)](//packagist.org/packages/patchlevel/event-sourcing)

# event-sourcing

Small lightweight event-sourcing library.

## installation

```
composer require patchlevel/event-sourcing
```

## define aggregates

```php
<?php declare(strict_types=1);

namespace App\Domain\Profile;

use App\Domain\Profile\Event\MessagePublished;
use App\Domain\Profile\Event\ProfileCreated;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Profile extends AggregateRoot
{
    private ProfileId $id;
    private Email $email;
    /** @var array<Message> */
    private array $messages;

    public function email(): Email
    {
        return $this->email;
    }

    /**
     * @return array<Message>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public static function createProfile(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->record(ProfileCreated::raise($id, $email));

        return $self;
    }

    public function publishMessage(Message $message): void
    {
        $this->record(MessagePublished::raise(
            $this->id,
            $message,
        ));
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
        $this->email = $event->email();
        $this->messages = [];
    }

    protected function applyMessagePublished(MessagePublished $event): void
    {
        $this->messages[] = $event->message();
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
```

## define events

```php
<?php declare(strict_types=1);

namespace App\Domain\Profile\Event;

use App\Domain\Profile\Email;
use App\Domain\Profile\ProfileId;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class ProfileCreated extends AggregateChanged
{
    public static function raise(
        ProfileId $id,
        Email $email
    ): AggregateChanged {
        return self::occur(
            $id->toString(),
            [
                'profileId' => $id->toString(),
                'email' => $email->toString(),
            ]
        );
    }

    public function profileId(): ProfileId
    {
        return ProfileId::fromString($this->aggregateId);
    }

    public function email(): Email
    {
        return Email::fromString($this->payload['email']);
    }
}
```

# define projections

```php
<?php declare(strict_types=1);

namespace App\ReadModel\Projection;

use const DATE_ATOM;
use App\Domain\Profile\Event\MessagePublished;
use App\Infrastructure\MongoDb\MongoDbManager;
use Patchlevel\EventSourcing\Projection\Projection;

final class MessageProjection implements Projection
{
    private MongoDbManager $db;

    public function __construct(MongoDbManager $db)
    {
        $this->db = $db;
    }

    public static function getHandledMessages(): iterable
    {
        yield MessagePublished::class => 'applyMessagePublished';
    }

    public function applyMessagePublished(MessagePublished $event): void
    {
        $message = $event->message();

        $this->db->collection('message')->insertOne([
            '_id' => $message->id()->toString(),
            'profile_id' => $event->profileId()->toString(),
            'text' => $message->text(),
            'created_at' => $message->createdAt()->format(DATE_ATOM),
        ]);
    }

    public function drop(): void
    {
        $this->db->collection('message')->drop();
    }
}
```

## usage

```php
<?php declare(strict_types=1);

namespace App\Domain\Profile\Handler;

use App\Domain\Profile\Command\CreateProfile;
use App\Domain\Profile\Profile;
use App\Domain\Profile\ProfileRepository;

final class CreateProfileHandler
{
    private ProfileRepository $profileRepository;

    public function __construct(ProfileRepository $profileRepository)
    {
        $this->profileRepository = $profileRepository;
    }

    public function __invoke(CreateProfile $command): void
    {
        $profile = Profile::createProfile($command->profileId(), $command->email());

        $this->profileRepository->store($profile);
    }
}
```
