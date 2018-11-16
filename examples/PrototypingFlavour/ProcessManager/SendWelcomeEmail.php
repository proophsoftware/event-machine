<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\PrototypingFlavour\ProcessManager;

use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Messaging\MessageDispatcher;

final class SendWelcomeEmail
{
    /**
     * @var MessageDispatcher
     */
    private $messageDispatcher;

    public function __construct(MessageDispatcher $messageDispatcher)
    {
        $this->messageDispatcher = $messageDispatcher;
    }

    public function __invoke(Message $event)
    {
        $this->messageDispatcher->dispatch('SendWelcomeEmail', ['email' => $event->get('email')]);
    }
}
