# prooph software Event Machine

## Event Sourced RAD

You think rapid application development and event sourcing don't play nice together? This package 
prooph you wrong :)

prooph software Event Machine takes away all the boring, time consuming parts of event sourcing to speed up
development of event sourced applications and increase the fun. It can be used for prototypes as well as full featured applications.

## Beginner friendly

> The Dreyfus model distinguishes five levels of competence, from novice to mastery. At the absolute beginner level people execute tasks based on “rigid adherence to taught rules or plans”. Beginners need recipes. They don’t need a list of parts, or a dozen different ways to do the same thing. Instead what works are step by step instructions that they can internalize. As they practice them over time they learn the reasoning behind them, and learn to deviate from them and improvise, but they first need to feel like they’re doing something.

(source: https://lambdaisland.com/blog/25-05-2017-simple-and-happy-is-clojure-dying-and-what-has-ruby-got-to-do-with-it)


## Nothing comes for free

**Be warned!** You have to pay a high price for RAD. Please consider the following cons before doing anything with Event Machine

- high coupling to Event Machine
- Event Machine has slower execution time, needs more CPU cycles and has a higher memory footprint than a normal event sourced application developed with prooph
- domain model is not designed with objects but with functions executed by Event Machine
- Event Machine only supports a single event stream for all events
- Command and Event validation is based on Json Schema, no other validation mechanism available
- You only get access to command and event payload, everything else is handled by Event Machine internally
- You cannot use one aggregate as a factory for another aggregate

## Ok, got the cons, but what are the pros?

- Event Machine ships with a default set up based on the rich features provided by prooph components
- you can start with a ready-to-use skeleton 
- No command and event classes needed
- No aggregate classes, repositories and no configuration needed
- programmatic message routing, again zero configuration
- No PHP class names used for messages or aggregate types (easy refactoring possible)
- Still a lot of extension points to inject custom logic
- Event Machine can be removed later when project grows and more time/budget is available to get the most **performance, flexibility and explicit modeling** out of event sourcing
- Audit log from day one (no data loss)
- Replay functionality available 
- Projections based on domain events
- Async messaging with rabbitMQ included in default set up (can be changed)
- PSR friendly http message box with api docs for available messages
- tbc

## Installation

*This package is under heavy development and not ready for usage. Watch the repo and keep up with the development. We'll tag a first dev version soon.*

