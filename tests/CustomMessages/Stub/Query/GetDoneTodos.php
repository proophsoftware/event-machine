<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Query;

use Prooph\Common\Messaging\PayloadTrait;
use Prooph\Common\Messaging\Query;

final class GetDoneTodos extends Query
{
    use PayloadTrait;

    public function todoId(): string
    {
        return $this->payload['todoId'];
    }
}
