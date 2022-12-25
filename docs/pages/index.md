# Event-Sourcing

A lightweight but also all-inclusive event sourcing library with a focus on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* [Snapshots](snapshots.md) and [Split-Stream](split_stream.md) system to quickly rebuild the aggregates
* [Pipeline](pipeline.md) to build new [projections](projection.md) or to migrate events
* [Projectionist](projectionist.md) for managed, versioned and asynchronous projections
* [Scheme management](store.md) and [doctrine migration](migration.md) support
* Dev [tools](watch_server.md) such as a realtime event watcher
* Built in [cli commands](cli.md) with [symfony](https://symfony.com/)

## Installation

```bash
composer require patchlevel/event-sourcing
```

## Integration

* [Symfony](https://github.com/patchlevel/event-sourcing-bundle)
* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)
