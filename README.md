# prooph software Event Machine

## Event Sourced RAD

You think rapid application development and event sourcing don't play nice together? This package 
prooph you wrong :)

prooph software Event Machine takes away all the boring, time consuming parts of event sourcing to speed up
the development of event sourced applications. It can be used for prototypes as well as full featured applications.

## Nothing comes for free

**Be warned!** You have to pay a high price for RAD. Please consider the following cons before doing anything with Event Machine

- high coupling to Event Machine
- Event Machine has slightly slower execution time and a higher memory footprint than a normal event sourced application developed with prooph
- domain model is not designed with objects but with functions executed by Event Machine
- Event Machine only supports a single event stream (a stream per aggregate type or even per aggregate is not possible)
- Command and Event validation is based on Json Schema, no other validation mechanism available
- You only get access to command and event payload, everything else is handled by Event Machine internally

## Ok, got the cons, but what are the pros?

prooph software Event Machine is designed based on years of experience with event sourced applications.
Our continuous support in the gitter chat, many discussions and some workshops have shown that 
event sourcing is one of the best ways to design a software system. But for many developers this concept is hard to learn
because it is so much different from what they learned in past projects and/or at university/school. 

That's one of the two problems. The second problem is, that even if developers want to learn event sourcing 
they don't know where to start or don't see the benefit fast enough because everything is sooo different.
Event Machine makes it easy for beginners and/or small teams to develop event sourced applications without the usual boilerplate caused by event sourcing.
You can focus on the core principles and Event Machine will handle the rest for you.

- Event Machine ships with a default set up based on the rich features provided by prooph components
- you can start with just spinning up the docker containers and writing your aggregate functions
- No command and event classes needed
- No aggregate classes, repositories and no configuration needed
- programmatic message routing, again zero configuration
- No PHP class names used for messages or aggregate types (easy refactoring possible)
- Still a lot of extension points to inject custom logic
- Event Machine can be removed later when project grows and more time/budget is available to get the most performance and flexibility out of event sourcing
- Audit log from day one (no data loss)
- Replay functionality available 
- Projections based on domain events
- Async messaging with rabbitMQ included in default set up (can be changed)
- tbc


## Installation

```bash
docker run --rm -it -v $(pwd):/app prooph/composer:7.1 install
docker-compose up -d
docker-compose run php php scripts/create_event_stream.php
```
