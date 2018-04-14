# Introduction

Event Machine is a rapid application development (RAD) framework so let us jump directly into
the practical part of the tutorial. Event Machine basic concepts will be explained throughout the tutorial.
Once finished, you should be able to start with your own project. The API docs will help you along the way.

## Workshops And Commercial Support

Our team can help you to take the first steps and work out a solid foundation for your project based on the power
and speed offered by Event Machine.
If you're interested in workshops or commercial support, [get in touch](http://getprooph.org/#get-in-touch).

## Tutorial Domain

We will build a backend for a small application where you can register `buildings` and then `check in` and `check out`
users in the buildings. The backend will expose a messagebox endpoint that excepts commands and queries.
Each time a user is `checked in` or `checked out` we get a notification via a websocket connection.

*Credits: The tutorial domain is the same as the one used by Marco Pivetta in his CQRS and Event Sourcing Workshops.*

## Application set up

Please make sure you have [Docker](https://docs.docker.com/engine/installation/ "Install Docker") and [Docker Compose](https://docs.docker.com/compose/install/ "Install Docker Compose") installed.

*Note: Docker is THE ONLY supported set up at the moment. If you don't want to install docker you need PHP 7.1+ and Postgres 9.4+.*

### Clone Event Machine Skeleton

Change into your working directory and use `composer` to create a new project based on the [event machine skeleton](https://github.com/proophsoftware/event-machine-skeleton)
using `prooph-em-buildings` as project name.

```bash
$ docker run --rm -it -v $(pwd):/app prooph/composer:7.1 create-project proophsoftware/event-machine-skeleton prooph-em-buildings
```

Change into the newly created project dir `prooph-em-buildings`, start the docker containers and run the set up script
for the event store.

```bash
$ cd prooph-em-buildings
$ sudo chown -R $(id -u -n):$(id -g -n) .
$ docker-compose up -d
$ docker-compose run php php scripts/create_event_stream.php
```
The last command should output `done.` otherwise it will throw an exception.

### Verify set up

#### Database
Verify database set up by connecting to the Postgres database using the credentials defined in `app.env`.
You should see three tables: `event_streams`, `projections` and `_<sha1>`. The latter is a table created by `prooph/event-store`.
It will contain all `domain events`.

#### Webserver
Head over to `http://localhost:8080` to check if the containers are up and running.
You should see a "It works" message.

#### Swagger UI
By default Event Machine exposes commands (we will learn more about them in a minute), events and queries via a message box endpoint.
We can use [Swagger UI](https://swagger.io/swagger-ui/) to interact with the backend. 

The Event Machine skeleton ships with a ready to use Swagger UI. Open [http://localhost:8080/swagger/index.html](http://localhost:8080/swagger/index.html)
in your browser and try the built-in `HealthCheck` query.

You should get a JSON response similar to that one:

```json
{
  "system": true
}
```

If everything works correctly we are ready to implement our first use case: **Add a building**

*Note: If something is not working as expected (now or later in the tutorial) please check the trouble shooting section of the event-machine-skeleton README first.*








