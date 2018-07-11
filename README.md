# prooph software Event Machine

[![Build Status](https://travis-ci.org/proophsoftware/event-machine.svg?branch=master)](https://travis-ci.org/proophsoftware/event-machine)
[![Coverage Status](https://coveralls.io/repos/github/proophsoftware/event-machine/badge.svg?branch=master)](https://coveralls.io/github/proophsoftware/event-machine?branch=master)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/proophsoftware/chat)

## Important!!!

Please check the notes of the [v1.0-alpha.6 Release](https://github.com/proophsoftware/event-machine/releases/tag/v1.0-alpha.6). We're going to change some parts of Event Machine before first stable release.

## Event Sourced RAD

prooph software Event Machine takes away all the boring, time consuming parts of event sourcing to speed up
development of event sourced applications and increase the fun. It can be used for prototypes as well as full featured applications.

## Installation

Head over to the [skeleton](https://github.com/proophsoftware/event-machine-skeleton)!

## Tutorial

[https://proophsoftware.github.io/event-machine/tutorial/](https://proophsoftware.github.io/event-machine/tutorial/)

## Documentation

Documentation is [in the docs tree](docs/), and can be compiled using [bookdown](http://bookdown.io) and [Docker](https://www.docker.com/).

```bash
$ docker run --rm -it -v $(pwd):/app prooph/composer:7.1
$ docker run -it --rm -e CSS_BOOTSWATCH=lumen -e CSS_PRISM=ghcolors -v $(pwd):/app sandrokeil/bookdown:develop docs/bookdown.json
$ docker run -it --rm -p 8080:8080 -v $(pwd):/app php:7.1-cli php -S 0.0.0.0:8080 -t /app/docs/html
```
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
