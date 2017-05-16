<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Command;

use Prooph\Common\Messaging\Command;
use Prooph\Common\Messaging\PayloadTrait;
use Prooph\EventStore\Util\Assertion;
use Prooph\Workshop\Model\User\UserId;

final class ChangeUsername extends Command
{
    use PayloadTrait;

    protected function setPayload(array $payload): void
    {
        Assertion::keyExists($payload, "userId");
        Assertion::keyExists($payload, "username");

        Assertion::uuid($payload['userId']);
        Assertion::notEmpty($payload['username']);

        $this->payload = $payload;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->payload['userId']);
    }

    public function username(): string
    {
        return $this->payload['username'];
    }
}
