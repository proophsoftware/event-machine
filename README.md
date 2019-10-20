# prooph software Event Machine

[![Build Status](https://travis-ci.org/proophsoftware/event-machine.svg?branch=master)](https://travis-ci.org/proophsoftware/event-machine)
[![Coverage Status](https://coveralls.io/repos/github/proophsoftware/event-machine/badge.svg?branch=master)](https://coveralls.io/github/proophsoftware/event-machine?branch=master)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/proophsoftware/chat)

**Superseded by [Event Engine](https://event-engine.io/)**

## Event Engine

Event Engine is a newer version of Event Machine with a different name but the same basic concepts.
It's recommended to use Event Engine, because development of Event Machine is not continued (except bugfixes).

Check the note in the [Event Engine readme](https://github.com/event-engine/php-engine#supersedes-event-machine) for further information.

## Intro

Event Machine is a CQRS / EventSourcing framework for PHP to help you rapidly develop event sourced applications, while providing a path to refactor towards a richer domain model as needed. Customize Event Machine with Flavours. Choose between different programming styles.

## Choose Your Flavour

![Choose Your Flavour](https://proophsoftware.github.io/event-machine/img/Choose_Flavour.png)

## Event Sourcing Engine

![Choose Your Flavour](https://proophsoftware.github.io/event-machine/api/img/Aggregate_Lifecycle.png)

## Installation

Head over to the [skeleton](https://github.com/proophsoftware/event-machine-skeleton)!

## Tutorial

[![Tutorial](https://proophsoftware.github.io/event-machine/img/tutorial_screen.png)](https://proophsoftware.github.io/event-machine/tutorial/)

**[GET STARTED](https://proophsoftware.github.io/event-machine/tutorial/)**

## Documentation

Source of the docs is managed in a separate [repo](https://github.com/proophsoftware/event-machine-docs)

## Run Tests

Some tests require existence of prooph/event-store tests which are usually not installed due to `.gitattributes` excluding them.
Unfortunately, composer does not offer a reinstall command so we have to remove `prooph/event-store` package from the vendor folder
manually and install it again using `--prefer-source` flag.

```bash
$ rm -rf vendor/prooph/event-store
$ docker run --rm -it -v $(pwd):/app --user="$(id -u):$(id -g)" prooph/composer:7.1 install --prefer-source
```


## Powered by prooph software

[![prooph software](https://github.com/codeliner/php-ddd-cargo-sample/blob/master/docs/assets/prooph-software-logo.png)](http://prooph.de)

Event Machine is maintained by the [prooph software team](http://prooph-software.de/). The source code of Event Machine 
is open sourced along with an API documentation and a [Getting Started Tutorial](#). Prooph software offers commercial support and workshops
for Event Machine as well as for the [prooph components](http://getprooph.org/).

If you are interested in this offer or need project support please [get in touch](http://getprooph.org/#get-in-touch)
