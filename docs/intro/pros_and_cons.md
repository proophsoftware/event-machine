# Pros and cons

## Pros

- Event Machine ships with a default set up based on the rich features provided by prooph components
- you can start with a ready-to-use [skeleton](https://github.com/proophsoftware/event-machine-skeleton) 
- Using a message class for each message is not needed (but can be used)
- Programmatic message routing
- A lot of extension points to inject custom logic
- Event Machine can be removed later when project grows and more time/budget is available to get the most **performance, flexibility and explicit modeling** out of event sourcing
- Audit log from day one (no data loss)
- Replay functionality available 
- Projections based on domain events
- Async messaging with rabbitMQ included in default set up (can be changed)
- PSR friendly http message box 
- GraphQL integration (plays well with a JavaScript frontend)
- Message flow analyzer (work in progress)
- Event store HTTP API (work in progress)

## Cons

Please consider the following cons before working with Event Machine

- Event Machine is an opinionated framework
- Infrastructure code is coupled with Event Machine (message validation and routing, aggregate and projection handling, ...)
- Programmatic set up that needs caching in production environments
- Message validation is based on Json Schema
- Read model is eventually consistent (you can achieve strong consistency but that's not recommended for this type of application)

## Conclusion

Reading through the pros and cons you may ask yourself when and why you should use Event Machine instead of working only with *prooph components*.
Here is a list of hints that may help you find a decision but please note that a good choice highly depends on the project, requirements
and the team. You should definitely try the tutorial and built a prototype with Event Machine! 
(if you want, you can [contact us](http://getprooph.org/#get-in-touch) for a guided workshop):

### You can use Event Machine if:

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



