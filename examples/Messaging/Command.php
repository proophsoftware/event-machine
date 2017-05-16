<?php
declare(strict_types = 1);

namespace ProophExample\Messaging;

final class Command
{
    const REGISTER_USER = 'RegisterUser';
    const CHANGE_USERNAME = 'ChangeUsername';

    private function __construct()
    {
        //static class only
    }
}
