<?php
declare(strict_types=1);

namespace ProophExample\Messaging;

final class Query
{
    const GET_USER = 'GetUser';
    const GET_USERS = 'GetUsers';
    const GET_FILTERED_USERS = 'GetFilteredUsers';

    private function __construct()
    {
        //static class only
    }
}
