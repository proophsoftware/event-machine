<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\PrototypingFlavour\Projector;

use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Messaging\Message;
use Prooph\EventMachine\Persistence\DocumentStore;
use Prooph\EventMachine\Projecting\Projector;
use ProophExample\PrototypingFlavour\Messaging\Event;

final class RegisteredUsersProjector implements Projector
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    public function handle(string $appVersion, string $projectionName, Message $event): void
    {
        switch ($event->messageName()) {
            case Event::USER_WAS_REGISTERED:
                $this->documentStore->addDoc($projectionName . '_' . $appVersion, $event->get('userId'), [
                    'userId' => $event->get('userId'),
                    'username' => $event->get('username'),
                    'email' => $event->get('email'),
                ]);
                break;
            default:
                throw new RuntimeException('Cannot handle event: ' . $event->messageName());
        }
    }

    public function prepareForRun(string $appVersion, string $projectionName): void
    {
        $this->documentStore->addCollection($projectionName . '_' . $appVersion);
    }

    public function deleteReadModel(string $appVersion, string $projectionName): void
    {
        $this->documentStore->dropCollection($projectionName . '_' . $appVersion);
    }
}
