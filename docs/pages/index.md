# Event-Sourcing

A lightweight but also all-inclusive event sourcing library with a focus on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* [Snapshots](snapshots.md) system to quickly rebuild the aggregates
* Allow to [Split-Stream](split_stream.md) for big aggregates
* [Pipeline](pipeline.md) to export,import and migrate events
* [Projectionist](projectionist.md) to manage versioned projections
* [Upcast](upcasting.md) old events
* [Scheme management](store.md) and [doctrine migration](migration.md) support
* Dev [tools](watch_server.md) such as a realtime event watcher
* Built in [cli commands](cli.md) with [symfony](https://symfony.com/)
* and much more...

## Installation

```bash
composer require patchlevel/event-sourcing
```

## Integration

* [Symfony](https://github.com/patchlevel/event-sourcing-bundle)
* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)
