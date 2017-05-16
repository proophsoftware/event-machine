<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Command;

use Prooph\Workshop\Infrastructure\Util\MessageName;
use Prooph\Workshop\Model\Configuration\Node;
use Prooph\Workshop\Model\User\UserId;
use Ramsey\Uuid\Uuid;

final class StartNewConfig extends AbstractJsonSchemaCommand
{

    /**
     * @return array
     */
    function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['configurationId', 'startNode', 'endNode', 'userId'],
            'properties' => [
                'configurationId' => [
                    'type' => 'string',
                    'minLength' => 36
                ],
                'startNode' => [
                    'type' => 'object',
                    'properties' => [
                        'hasStart' => [
                            'enum' => [true],
                        ],
                        'hasEnd' => [
                            'enum' => [false],
                        ],
                    ],
                ],
                'endNode' => [
                    'type' => 'object',
                    'properties' => [
                        'hasStart' => [
                            'enum' => [false],
                        ],
                        'hasEnd' => [
                            'enum' => [true],
                        ],
                    ],
                ],
                'userId' => [
                    'type' => 'string',
                    'minLength' => 36
                ],
            ],
        ];
    }

    public function configurationId(): Uuid
    {
        return Uuid::fromString($this->payload['configurationId']);
    }

    public function startNode(): Node
    {
        return Node::fromArray($this->payload['startNode']);
    }

    public function endNode(): Node
    {
        return Node::fromArray($this->payload['endNode']);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->payload['userId']);
    }
}
