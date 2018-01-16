<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Data;

interface DataConverter
{
    public function convertDataToArray($data): array;
}
