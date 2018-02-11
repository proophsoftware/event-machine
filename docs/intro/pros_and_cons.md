# The pros and cons

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
- Domain model is not designed with objects but with functions executed by Event Machine (differs from OOP)
- Message validation is based on Json Schema
- Read model is eventually consistent (you can achieve strong consistency but that's not recommended for this type of application)

