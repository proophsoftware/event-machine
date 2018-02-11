<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Messaging;

use Prooph\Common\Messaging\Message as ProophMessage;

interface Message extends ProophMessage
{
    /**
     * Get $key from message payload
     *
     * @param string $key
     * @throws \BadMethodCallException if key does not exist in payload
     * @return mixed
     */
    public function get(string $key);

    /**
     * Get $key from message payload or default in case key does not exist
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOrDefault(string $key, $default);
}
