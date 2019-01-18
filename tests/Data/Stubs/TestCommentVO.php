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
