<?php
declare(strict_types = 1);

namespace ProophExample\Messaging;

final class Event
{
    const USER_WAS_REGISTERED = 'UserWasRegistered';
    const USER_REGISTRATION_FAILED = 'UserRegistrationFailed';
    const USERNAME_WAS_CHANGED = 'UsernameWasChanged';

    private function __construct()
    {
        //static class only
    }
}
