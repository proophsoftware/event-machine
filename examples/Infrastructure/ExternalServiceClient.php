<?php

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
