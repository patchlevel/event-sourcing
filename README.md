[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing%2F2.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing/2.0.x)
[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing/v)](//packagist.org/packages/patchlevel/event-sourcing)
[![License](https://poser.pugx.org/patchlevel/event-sourcing/license)](//packagist.org/packages/patchlevel/event-sourcing)

# Event-Sourcing

A lightweight but also all-inclusive event sourcing library with a focus on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* [Snapshots](https://patchlevel.github.io/event-sourcing-docs/latest/snapshots/) system to quickly rebuild the aggregates
* [Pipeline](https://patchlevel.github.io/event-sourcing-docs/latest/pipeline/) to build new [projections](https://patchlevel.github.io/event-sourcing-docs/latest/projection/) or to migrate events
* [Scheme management](https://patchlevel.github.io/event-sourcing-docs/latest/store/) and [doctrine migration](https://patchlevel.github.io/event-sourcing-docs/latest/migration/) support
* Dev [tools](https://patchlevel.github.io/event-sourcing-docs/latest/watch_server/) such as a realtime event watcher
* Built in [cli commands](https://patchlevel.github.io/event-sourcing-docs/latest/cli/) with [symfony](https://symfony.com/)

## Installation

```bash
composer require patchlevel/event-sourcing
```

## Documentation

* Latest [Docs](https://patchlevel.github.io/event-sourcing-docs/latest)
* 1.3 [Docs](https://github.com/patchlevel/event-sourcing/blob/1.3.x/README.md)

## Integration

* [Symfony](https://github.com/patchlevel/event-sourcing-bundle)
* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)
