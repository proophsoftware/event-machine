<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Data;

final class ImmutableRecordDataConverter implements DataConverter
{
    public function convertDataToArray($data): array
    {
        if(is_array($data)) {
            return $data;
        }

        if($data instanceof ImmutableRecord) {
            return $data->toArray();
        }

        return (array)json_decode(json_encode($data), true);
    }
}
