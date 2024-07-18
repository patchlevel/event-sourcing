[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing%2F3.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing/3.0.x)
[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing/v)](//packagist.org/packages/patchlevel/event-sourcing)
[![License](https://poser.pugx.org/patchlevel/event-sourcing/license)](//packagist.org/packages/patchlevel/event-sourcing)

# Event-Sourcing

An event sourcing library, complete with all the essential features, 
powered by the reliable Doctrine ecosystem and focused on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* Automatic [snapshot](https://patchlevel.github.io/event-sourcing-docs/latest/snapshots/)-system to boost your performance
* [Split](https://patchlevel.github.io/event-sourcing-docs/latest/split_stream/) big aggregates into multiple streams
* Versioned and managed lifecycle of [subscriptions](https://patchlevel.github.io/event-sourcing-docs/latest/subscription/) like projections and processors
* Safe usage of [Personal Data](https://patchlevel.github.io/event-sourcing-docs/latest/personal_data/) with crypto-shredding
* Smooth [upcasting](https://patchlevel.github.io/event-sourcing-docs/latest/upcasting/) of old events
* Simple setup with [scheme management](https://patchlevel.github.io/event-sourcing-docs/latest/store/) and [doctrine migration](https://patchlevel.github.io/event-sourcing-docs/latest/store/)
* Built in [cli commands](https://patchlevel.github.io/event-sourcing-docs/latest/cli/) with [symfony](https://symfony.com/)
* and much more...

## Installation

```bash
composer require patchlevel/event-sourcing
```

## Documentation

* Latest [Docs](https://patchlevel.github.io/event-sourcing-docs/latest)

## Integration

* [Symfony](https://github.com/patchlevel/event-sourcing-bundle)
* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)

## Supported databases

We officially only support the databases and versions listed in the table, as these are tested in the CI.
Since the package is based on doctrine dbal, other databases such as OracleDB and MSSQL may also work.
But we can only really support the databases if we can also automatically ensure that they don't break due to changes.

> [!TIP]
> We recommend using PostgreSQL.

| Database    | Version                        |
|-------------|--------------------------------|
| PostgreSQL | 12.19, 13.15, 14.12, 15.7, 16.3 |
| MariaDB     | 10.5, 10.6, 10.11, 11.1, 11.4  |
| MySQL       | 5.7, 8.0, 8.4, 9.0             |
| SQLite      | 3.x                            |

## Sponsors

[<img src="https://github.com/patchlevel/event-sourcing/assets/470138/d00b7459-23b7-431b-80b4-93cfc1b66216" alt="blackfire" width="200">](https://www.blackfire.io)
