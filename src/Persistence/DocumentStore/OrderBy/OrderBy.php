<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore\OrderBy;

interface OrderBy
{
    const ASC = 'asc';
    const DESC = 'desc';

    public static function fromArray(array $data): OrderBy;

    public function toArray(): array;
}
