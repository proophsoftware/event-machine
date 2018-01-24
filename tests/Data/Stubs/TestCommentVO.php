<?php
declare(strict_types=1);

namespace Prooph\EventMachineTest\Data\Stubs;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;

final class TestCommentVO implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var string
     */
    private $text;

    /**
     * @var TestUserVO|null
     */
    private $user;

    /**
     * @return string
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * @return null|TestUserVO
     */
    public function user(): ?TestUserVO
    {
        return $this->user;
    }
}
