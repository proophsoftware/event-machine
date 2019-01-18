<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\PrototypingFlavour\Messaging;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;

final class CommandWithCustomHandler implements EventMachineDescription
{
    public const CMD_DO_NOTHING = 'DoNothing';
    public const NO_OP_HANDLER = 'NoOpHandler';

    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->registerCommand(self::CMD_DO_NOTHING, JsonSchema::object(['msg' => JsonSchema::string()]));
        $eventMachine->preProcess(self::CMD_DO_NOTHING, self::NO_OP_HANDLER);
    }
}
