<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Command;

use Assert\Assertion;
use Prooph\Common\Messaging\Command;
use Prooph\Common\Messaging\PayloadTrait;
use Prooph\Workshop\Model\User\UserId;

final class RegisterUser extends AbstractJsonSchemaCommand
{
    public function userId(): UserId
    {
        return UserId::fromString($this->payload['userId']);
    }

    public function username(): string
    {
        return $this->payload['username'];
    }

    public function email(): string
    {
        return $this->payload['email'];
    }

    /**
     * @return array
     */
    function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'userId' => [
                    'type' => 'string',
                    'minLength' => 36
                ],
                'username' => [
                    'type' => 'string',
                    'minLength' => 1
                ],
                'email' => [
                    'type' => 'string',
                    'format' => 'email'
                ]
            ],
            'required' => [
                'userId',
                'username',
                'email'
            ]
        ];
    }
}
