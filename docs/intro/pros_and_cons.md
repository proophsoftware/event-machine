# Pros and cons

## Pros

- Default set up based on the rich features provided by prooph components
- Ready-to-use [skeleton](https://github.com/proophsoftware/event-machine-skeleton)
- Less code needed
- Programmatic message routing
- extension points to inject custom logic
- Audit log from day one (no data loss)
- Replay functionality
- Projections based on domain events
- PSR friendly http message box
- OpenAPI v3 Swagger integration
- Message flow analyzer (work in progress)
- Event store HTTP API (work in progress)

## Cons

- Opinionated framework

## Conclusion

Reading through the pros and cons you may ask yourself when and why you should use Event Machine instead of working only with *prooph components*.
Here is a list of hints that may help you make a decision but please note that the right choice is highly dependent on the project, requirements
and the team. You should definitely try the tutorial and build a prototype with Event Machine!
(If you are interested, you can [contact us](http://getprooph.org/#get-in-touch) for a guided workshop):

### You may want to use Event Machine if:

- You're **new to the concepts** of CQRS and Event Sourcing and want to learn them
- You want to try CQRS and Event Sourcing in a side project **without spending too much time** with the theory
- You **hate boilerplate** but have no time to develop your own Event Machine
- You want to make use of **advanced tooling** provided by prooph software that requires Event Machine
- You want to establish a **service-oriented architecture** rather than building a monolithic system
- Your project is in an early stage and you need to try out different ideas or **deliver features very fast**
- You're **practicing Domain-Driven Design** and the service you're building belongs to a supporting sub domain
- You don't want to fight your framework but **get the most out of it**
- You're using a modern JavaScript framework in the frontend and need an **API-only backend**

*Note: Even if Event Machine is opinionated it is designed to support loose coupling between different parts of an application.
This means that if you start a project using Event Machine but it gets in your way later you can get rid of it step by step
(continuous refactoring).*



