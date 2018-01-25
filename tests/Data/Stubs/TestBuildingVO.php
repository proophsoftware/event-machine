<?php
declare(strict_types=1);

namespace Prooph\EventMachineTest\Data\Stubs;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;

final class TestBuildingVO implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $type = 'house';

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }
}
