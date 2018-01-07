<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore\Filter;

interface Filter
{
    public const NOT_SET_PROPERTY = '___EVENT_MACHINE_FILTER_NOT_SET_PROPERTY___';

    public function match(array $doc): bool;
}
