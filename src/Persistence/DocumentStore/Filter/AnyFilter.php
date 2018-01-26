<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore\Filter;

final class AnyFilter implements Filter
{
    public function match(array $doc): bool
    {
        return true;
    }
}
