# About Event Machine

prooph software Event Machine takes away all the boring, time consuming parts of event sourcing to speed up
development of event sourced applications and increase the fun. It can be used for prototypes as well as full featured applications.


## Origin

Event Machine was originally designed as a "workshop framework".

### Beginner friendly

> The Dreyfus model distinguishes five levels of competence, from novice to mastery. At the absolute beginner level people execute tasks based on “rigid adherence to taught rules or plans”. Beginners need recipes. They don’t need a list of parts, or a dozen different ways to do the same thing. Instead what works are step by step instructions that they can internalize. As they practice them over time they learn the reasoning behind them, and learn to deviate from them and improvise, but they first need to feel like they’re doing something.

(source: https://lambdaisland.com/blog/25-05-2017-simple-and-happy-is-clojure-dying-and-what-has-ruby-got-to-do-with-it)

### Rapid Application Development
It turned out that event machine is not only a very good CQRS and Event Sourcing learning framework but that the same concept
can be used for rapid application development (short RAD). RAD frameworks share some common concepts. They focus on developer
happiness and coding speed. Both can be achieved by conventions which allow the framework to do a lot of stuff "under the hood"
so that developers can focus on the important part: **developing the application**.

Having said this, Event Machine can be compared with frameworks like Ruby on Rails or Laravel, but it also has a **unique selling point**.
Instead of working with a CRUD based approach, **Event Machine uses CQRS and Event Sourcing**. In fact it uses [prooph/components](http://getprooph.org)
under the hood. This enables intersting scenarios like **starting a project with a lean and rapid development** and switching to an enterprise
approach later. With Event Machine you get **separation of concern from day one**. You get separated write and read models which can be scaled
independent of each other and you also get **a full history of all state changes** so it is not only cheap to develop an application
with Event Machine but it's also cheap to maintain that application in production. 

## Nothing comes for free

**Be warned!** You have to pay a price for RAD. Please consider the following facts before working with Event Machine

- Event Machine is an opinionated framework
- Runtime is coupled to Event Machine
- Programmatic set up that needs caching in production environments
- Domain model is not designed with objects but with functions executed by Event Machine (not a real drawback but looks different)
- Command and Event validation is based on Json Schema, no other validation mechanism available (as already said, it's opinionated)
- Read model is eventually consistent (you can achieve strong consistency but that's not recommended for this type of application)

## Ok, got the cons, but what are the pros?

- Event Machine ships with a default set up based on the rich features provided by prooph components
- you can start with a ready-to-use [skeleton](https://github.com/proophsoftware/event-machine-skeleton) 
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
- PSR friendly http message box 
- GraphQL integration
- Message flow analyzer (work in progress)
- Event store HTTP API (work in progress)

## People behind the project

[![prooph software](https://github.com/codeliner/php-ddd-cargo-sample/raw/master/docs/assets/prooph-software-logo.png)](http://prooph.de)

Event Machine is maintained by the [prooph software team](http://prooph-software.de/). The source code of Event Machine 
is open sourced along with an API documentation and a [Getting Started Tutorial](#). Prooph software offers commercial support and workshops
for Event Machine as well as for the [prooph components](http://getprooph.org/).

If you are interested in this offer or need project support please [get in touch](http://getprooph.org/#get-in-touch)