<?php

declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore;

interface Index
{
    public const SORT_ASC = 1;
    public const SORT_DESC = -1;
}
