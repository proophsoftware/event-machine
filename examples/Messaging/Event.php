<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\Messaging;

final class Event
{
    const USER_WAS_REGISTERED = 'UserWasRegistered';
    const USER_REGISTRATION_FAILED = 'UserRegistrationFailed';
    const USERNAME_WAS_CHANGED = 'UsernameWasChanged';
    const EXTERNAL_SERVICE_WAS_CALLED = 'ExternalServiceWasCalled';

    private function __construct()
    {
        //static class only
    }
}
