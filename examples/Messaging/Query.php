<?php
declare(strict_types=1);

namespace ProophExample\Messaging;

final class Query
{
    const GET_USER = 'GetUser';
    const GET_USERS = 'GetUsers';

    private function __construct()
    {
        //static class only
    }
}
