<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Data;

final class ImmutableRecordDataConverter implements DataConverter
{
    public function convertDataToArray($data): array
    {
        if (\is_array($data)) {
            return $data;
        }

        if ($data instanceof ImmutableRecord) {
            return $data->toArray();
        }

        return (array) \json_decode(\json_encode($data), true);
    }
}
