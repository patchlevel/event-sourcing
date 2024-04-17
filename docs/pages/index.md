# Event-Sourcing

An event sourcing library, complete with all the essential features,
powered by the reliable Doctrine ecosystem and focused on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* Automatic [snapshot](snapshots.md)-system to boost your performance
* [Split](split_stream.md) big aggregates into multiple streams
* Versioned and managed lifecycle of [subscriptions](subscription.md) like projections and processors
* Safe usage of [Personal Data](personal_data.md) with crypto-shredding
* Smooth [upcasting](upcasting.md) of old events
* Simple setup with [scheme management](store.md) and [doctrine migration](store.md)
* Built in [cli commands](cli.md) with [symfony](https://symfony.com/)
* and much more...

## Installation

```bash
composer require patchlevel/event-sourcing
```
## Integration

* [Symfony](https://github.com/patchlevel/event-sourcing-bundle)
* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)

!!! tip

    Start with the [quickstart](./getting_started.md) to get a feeling for the library.
    