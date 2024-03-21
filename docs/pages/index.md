# Event-Sourcing

A lightweight but also all-inclusive event sourcing library with a focus on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* Automatic [snapshot](snapshots.md)-system to boost your performance
* [Split](split_stream.md) big aggregates into multiple streams
* Build-in [pipeline](pipeline.md) to export, import and migrate event streams
* Versioned and managed lifecycle of [subscriptions](subscription.md) like projections and processors
* Smooth [upcasting](upcasting.md) of old events
* Simple setup with [scheme management](store.md) and [doctrine migration](migration.md)
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
    