# Event-Sourcing

A lightweight but also all-inclusive event sourcing library with a focus on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* [Snapshots](snapshots.md) system to quickly rebuild the aggregates
* [Pipeline](pipeline.md) to build new [projections](projection.md) or to migrate events
* [Scheme management](store.md) and [doctrine migration](store.md) support
* Dev [tools](tools.md) such as a realtime event watcher
* Built in [cli commands](cli.md) with [symfony](https://symfony.com/)

## Installation

```bash
composer require patchlevel/event-sourcing
```
