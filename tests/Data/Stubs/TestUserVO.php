<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Data\Stubs;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;
use Prooph\EventMachine\JsonSchema\Type;

final class TestUserVO implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int|null
     */
    private $age;

    /**
     * @var TestIdentityVO[]
     */
    private $identities;

    public static function __schema(): Type
    {
        return self::generateSchemaFromPropTypeMap(['identities' => TestIdentityVO::class]);
    }

    /**
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return int|null
     */
    public function age(): ?int
    {
        return $this->age;
    }

    /**
     * @return TestIdentityVO[]
     */
    public function identities(): array
    {
        return $this->identities;
    }
}
