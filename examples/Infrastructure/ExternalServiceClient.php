<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\Infrastructure;

final class ExternalServiceClient
{
    public function retrieveData(string $userId): array
    {
        return [
            'userId' => $userId,
            'test' => 'succeeded',
        ];
    }
}
