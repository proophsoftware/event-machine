<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\CustomMessages\Stub\Query;

use React\Promise\Deferred;

final class TodoFinder
{
    private $lastQuery;

    public function __invoke($query, Deferred $deferred)
    {
        $this->lastQuery = $query;
    }

    public function getLastReceivedQuery()
    {
        return $this->lastQuery;
    }
}
