# About Event Machine

prooph software Event Machine takes away all the boring, time consuming parts of event sourcing to speed up
development of event sourced applications and increase the fun. It can be used for prototypes as well as full featured applications.


## Origin

Event Machine was originally designed as a "workshop framework".

### Beginner friendly

> The Dreyfus model distinguishes five levels of competence, from novice to mastery. At the absolute beginner level people execute tasks based on “rigid adherence to taught rules or plans”. Beginners need recipes. They don’t need a list of parts, or a dozen different ways to do the same thing. Instead what works are step by step instructions that they can internalize. As they practice them over time they learn the reasoning behind them, and learn to deviate from them and improvise, but they first need to feel like they’re doing something.

(source: https://lambdaisland.com/blog/25-05-2017-simple-and-happy-is-clojure-dying-and-what-has-ruby-got-to-do-with-it)

### Rapid Application Development
It turned out that Event Machine is not only a very good CQRS and Event Sourcing learning framework but that the same concept
can be used for rapid application development (short RAD). RAD frameworks share some common concepts. They focus on developer
happiness and coding speed. Both can be achieved by conventions which allow the framework to do a lot of stuff "under the hood"
so that developers can focus on the important part: **developing the application**.

Having said this, Event Machine can be compared with frameworks like Ruby on Rails or Laravel, but it also has a **unique selling point**.
Instead of working with a CRUD based approach, **Event Machine uses CQRS and Event Sourcing**. In fact it uses [prooph/components](http://getprooph.org)
under the hood. This enables interesting scenarios like **starting a project with a lean and rapid development approach** and switch to an enterprise
approach later. With Event Machine you get **separation of concern from day one**. You get separated write and read models which can be scaled
independent of each other and you also get **a full history of all state changes** so it is not only cheap to develop an application
with Event Machine but it's also cheap to maintain that application in production. 

## People behind the project

[![prooph software](https://github.com/codeliner/php-ddd-cargo-sample/raw/master/docs/assets/prooph-software-logo.png)](http://prooph.de)

Event Machine is maintained by the [prooph software team](http://prooph-software.de/). The source code of Event Machine 
is open sourced along with an API documentation and a [Getting Started Tutorial](#). Prooph software offers commercial support and workshops
for Event Machine as well as for the [prooph components](http://getprooph.org/).

If you are interested in this offer or need project support please [get in touch](http://getprooph.org/#get-in-touch)