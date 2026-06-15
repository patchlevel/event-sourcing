[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing%2F3.11.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing/3.11.x)
[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing/v)](//packagist.org/packages/patchlevel/event-sourcing)
[![License](https://poser.pugx.org/patchlevel/event-sourcing/license)](//packagist.org/packages/patchlevel/event-sourcing)

# Event-Sourcing

An event sourcing library, complete with all the essential features,
powered by the reliable Doctrine ecosystem and focused on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on doctrine dbal and their ecosystem
* Developer experience oriented and fully typed
* Automatic snapshot-system to boost your performance
* Split big aggregates into multiple streams
* Versioned and managed lifecycle of subscriptions like projections and processors
* Safe usage of personal data with crypto-shredding
* Smooth upcasting of old events
* Simple setup with schema management and doctrine migration
* Built in cli commands with symfony
* Dynamic consistency boundary for decisions across streams
* and much more...

## Installation

```bash
composer require patchlevel/event-sourcing
```

## Documentation

* Latest [Docs](https://patchlevel.dev/docs/event-sourcing/latest)
* Related [Blog](https://patchlevel.dev/blog)

## Integration

* [Symfony](https://github.com/patchlevel/event-sourcing-bundle)
* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)

## Contributing

We are open to contributions as long as they are in line with
our [BC-Policy](https://patchlevel.dev/our-backward-compatibility-promise).

Also note that the `composer.lock` is always generated with the newest supported PHP version as this is the version our tools run in the CI.

## Supported databases

We officially only support the databases and versions listed in the table, as these are tested in the CI.
Since the package is based on doctrine dbal, other databases such as OracleDB and MSSQL may also work.
But we can only really support the databases if we can also automatically ensure that they don't break due to changes.

> [!TIP]
> We recommend using PostgreSQL.

| Database   | Version                         |
|------------|---------------------------------|
| PostgreSQL | 14.20, 15.15, 16.11, 17.7, 18.1 |
| MariaDB    | 10.6, 10.11, 11.4, 11.8, 12.1   |
| MySQL      | 8.0, 8.4, 9.5                   |
| SQLite     | 3.x                             |

## Sponsors

[<img src="https://github.com/patchlevel/event-sourcing/assets/470138/d00b7459-23b7-431b-80b4-93cfc1b66216" alt="blackfire" width="200">](https://www.blackfire.io)
