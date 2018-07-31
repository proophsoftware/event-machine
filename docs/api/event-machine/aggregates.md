# Aggregates

> Event Sourced Aggregates are Domain-Driven Aggregates, representing a unit of consistency.
 They protect invariants. This basically means that an aggregate makes sure that it can transition to a new state.
 Different business rules can permit or prevent state transitions, and the aggregate has to enforce these business rules.

 *Source: http://docs.getprooph.org/tutorial/event_sourcing_basics.html#1-3-3*

When looking at the definition above one immediately starts thinking of an object with internal state and methods to change that state.
It's because we are so used to work with object oriented programming. In Event Machine (and prooph/micro as well) we stick to the same
basic definition of an aggregate but apply a functional approach. This means that we use **pure and stateless** functions and
**immutable data types** in favour of mutable objects.


## Why Stateless Functions and NOT Objects?

I'm glad you asked! Well, there is nothing wrong with using CQRS & Event Sourcing in an object oriented fashion. In fact prooph components and especially prooph/event-sourcing
are built on a solid OOP foundation. But it turned out that the idea of a **functional core** plays very very well with Event Sourcing. If you look at the core ideas
of Event Sourcing you'll recognize that it is heavily based on functional patterns: *immutable events*, *append-only streams*, *left fold of past events to calculate current state*, ...

If you accept that business logic does not necessarily require OOP patterns, you'll enter a new world of programming. Event Machine is one possible
implementation that handles a lot of boilerplate for you.
[prooph/micro](https://github.com/prooph/micro) is another - more lightweight - implementation, which gives you more freedom but also requires more design decisions.
The good news is, that it is very easy to migrate from Event Machine to prooph/micro. You can start with the simplicity of Event Machine and focus
on model exploration and "getting the business logic right". Once you have a working model but you need more scalability options or just want to decouple from a framework
as much as possible you can switch to prooph/micro and continue your journey.

Event Machine grows with your application from prototype and MVP up to a rock solid production system with a lot of flexibility and decoupled models. But at some point it might get
in your way, because increasing number of users and parallel operations force you to save every possible millisecond of execution time. At this time
prooph/micro is clearly the better choice.

Event Machine is able to reduce boilerplate code to a bare minimum because of a few "simple" rules:

### 1. Pure Functions

An aggregate function has no side effects. Given the same input the function will **always** produce the same result.

Here is the simplest form of a pure aggregate function in Event Machine:

#### prooph/micro style

```php
//some_business_process.php

declare(strict_types=1);

Namespace Acme\Model\SomeBusinessProcess;

use Prooph\EventMachine\Messaging\Message;
use Acme\Api\Event;

const startProcess = '\Acme\SomeBusinessProcess\startProcess';

function startProcess(Message $startProcess): \Generator {
    yield [Event::SOME_PROCESS_STARTED, $startProcess->payload()];
}

//More functions ....

```

#### Static method style

```php
//SomeBusinessProcess.php

declare(strict_types=1);

Namespace Acme\Model;

use Prooph\EventMachine\Messaging\Message;
use Acme\Api\Event;

final class SomeBusinessProcess
{
    public static function startProcess(Message $startProcess): \Generator
    {
        yield [Event::SOME_PROCESS_STARTED, $startProcess->payload()];
    }

    //more methods ....
}

```

As you can see both approaches are very similar. The *prooph/micro* style looks more functional and underlines the intention of the code whereby the
*static method* approach plays nice together with a modern IDE and PHP's autoloader. We'll stick to the static method approach in the examples because this is
the recommended style when working with Event Machine. But *prooph/micro* style can be used, too!

Back to the **pure** nature of both approaches. No matter how often you call the function as long as the input message payload does not change, the yielded
event won't change, too.

This property makes testing the function a breeze. You don't need mocks. You don't need heavy fixture setup. Just create the appropriate message, call the function and
test against an expected event.

### 2. Stateless Functions

No object, no internal state and therefor a much simpler business logic implementation, which is easy to test, refactor and maintain!

But even if we use functions, we have to be careful to not fall into the trap of modifying state:

#### Evil Global Variable

One way to break the rule with a function is by modifying global state.

```php
//SomeBusinessProcess.php

declare(strict_types=1);

Namespace Acme\Model;

use Prooph\EventMachine\Messaging\Message;
use Acme\Api\Event;

$evilState = new EvilState();

final class SomeBusinessProcess
{
    public static function startProcess(Message $startProcess): \Generator
    {
        global $evilState;

        $evilState->burnStatelessApproach();

        yield [Event::SOME_PROCESS_STARTED, $startProcess->payload()];
    }

    //more methods ....
}

```

**Never ever do this!** Don't even think about it!

#### Evil Static Property

Another way to break our stateless function:

```php
//SomeBusinessProcess.php

declare(strict_types=1);

Namespace Acme\Model;

use Prooph\EventMachine\Messaging\Message;
use Acme\Api\Event;

final class SomeBusinessProcess
{
    private static $evilState;

    public static function startProcess(Message $startProcess): \Generator
    {
        self::$evilState = new EvilState();

        yield [Event::SOME_PROCESS_STARTED, $startProcess->payload()];
    }

    public static funcion continueProcess(Message $continue): \Generator
    {
        yield [Event::SOME_PROCESS_CONTINUED, self::$evilState->toArray()];
    }

    //more methods ....
}

```

#### Evil Static Local Variable

That's also a very bad idea:

```php
//SomeBusinessProcess.php

declare(strict_types=1);

Namespace Acme\Model;

use Prooph\EventMachine\Messaging\Message;
use Acme\Api\Event;

final class SomeBusinessProcess
{
    public static function startProcess(Message $startProcess): \Generator
    {
        yield [Event::SOME_PROCESS_STARTED, $startProcess->payload()];
    }

    public static funcion continueProcess(Message $continue): \Generator
    {
        static $evilCounter;

        if($evilCounter === null) {
            $evilCounter = 0;
        }

        yield [Event::SOME_PROCESS_CONTINUED, ['counter' => ++$evilCounter]];
    }

    //more methods ....
}

```

#### Evil Mutable State Passed As Argument

Mutable state passed as an argument is probably the easiest way to break the stateless rule. Let's look at an evil example first and then we'll see how we
can do better.

```php
//SomeBusinessProcess.php

declare(strict_types=1);

Namespace Acme\Model;

use Prooph\EventMachine\Messaging\Message;
use Acme\Api\Event;

final class SomeBusinessProcess
{
    public static function startProcess(Message $startProcess): \Generator
    {
        yield [Event::SOME_PROCESS_STARTED, $startProcess->payload()];
    }

    public static funcion continueProcess(EvilMutableState $evilState, Message $continue): \Generator
    {
        $evilState->burnStatelessApproach();

        yield [Event::SOME_PROCESS_CONTINUED, ['state' => $evilState->toArray()]];
    }

    //more methods ....
}

```

Let's fight the evil.

```php
//SomeBusinessProcess.php

declare(strict_types=1);

Namespace Acme\Model;

use Prooph\EventMachine\Messaging\Message;
use Acme\Api\Event;
use Acme\Api\Payload;

final class SomeBusinessProcess
{
    public static function startProcess(Message $startProcess): \Generator
    {
        yield [Event::SOME_PROCESS_STARTED, $startProcess->payload()];
    }

    public static function whenProcessStarted(Message $processStarted): ImmutableState
    {
        return new ImmutableState();
    }

    public static funcion continueProcess(ImmutableState $state, Message $continue): \Generator
    {
        if($state->wantsToBurnStatelessApproach()) {
            yield [Event::STATE_MUTATION_BLOCKED, [Payload::ALTERNATIVE => $continue->get(Payload::ALTERNATIVE)]];
        }
    }

    public static function whenStateMutationBlocked(ImmutableState $currentState, Message $stateMutationBlocked): ImmutableState
    {
        return $currentState->withAlternativeMutation($stateMutationBlocked->get(Payload::ALTERNATIVE));
    }
}

```

Ok, we see two new functions here. Both start with `when` followed by an event name.

*Note: The naming is only a recommendation.*

Those when functions do not take commands as input and do not yield events, but instead take yielded events as input and **return** (note the difference to yield)
`ImmutableState`. The second when function even takes `ImmutableState` as an argument and returns it.

To be able to understand the alternative to a mutable state we have to jump into the method `withAlternativeMutation`:

```php
declare(strict_types=1);

Namespace Acme\Model;

final class ImmutableState
{
    private $alternative;

    public function alternative(): ?string
    {
        return $this->alternative;
    }

    public function withAlternativeMutation(string $alternative): self
    {
        $copy = clone $this;
        $copy->alternative = $alternative;
        return $copy;
    }
}

```

This is how state of an immutable value object is changed. Instead of modifying internal state directly, the value object copies itself and modfies the copy
instead of itself. It works because visibility of properties and methods is defined on class level and not on instance level.

Let's look at the effect with a unit test:

```php
declare(strict_types=1);

Namespace AcmeTest\Model;

use Acme\Api\Event;
use Acme\Api\Payload;
use Acme\Model\SomeBusinessProcess;
use Acme\Model\ImmutableState;
use AcmeTest\BaseTestCase; //<-- extends PHPUnit\Framework\TestCase + provides message factory
use Prooph\EventMachine\Messaging\Message;

final class SomeBusinessProcessTest extends BaseTestCase
{
    /**
     * @test
     */
     public function it_does_not_change_input_state()
     {
        $inputState = new ImmutableState();

        $this->assertNull($inputState->alternative());

        $event = $this->messageFactory()->createMessageFromArray(
            Event::STATE_MUTATION_BLOCKED,
            [
                'payload' => [
                    'alternative' => 'modify and return copy'
                ]
            ]
        );

        $outputState = SomeBusinessProcess::whenStateMutationBlocked(
            $inputState,
            $event
        );

        $this->assertNull($inputState->alternative());

        $this->assertSame(
            'modify and return copy',
            $outputState->alternative()
        );
     }
}

```



- The very **first** function of an aggregate life cycle takes a **command** message as first argument and optionally a **context** object as second
- All **subsequent** aggregate functions take current aggregate **state** as first argument, a **command** message as second argument and **context** as optional third argument
- All aggregate functions **yield zero, one or multiple events** as a result of command execution
- Aggregate state is **immutable**
- The very **first** aggregate **state apply function** takes the first yielded event of the aggregate as the only argument and returns a new aggregate **state**
- All **subsequent** aggregate **state apply functions** take current aggregate state as first argument, a yielded aggregate event as second argument and return a **new** state object representing
aggregate state **after** the event is applied



