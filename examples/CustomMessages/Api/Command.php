<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\CustomMessages\Api;

use ProophExample\CustomMessages\Command\ChangeUsername;
use ProophExample\CustomMessages\Command\RegisterUser;

final class Command
{
    const REGISTER_USER = 'RegisterUser';
    const CHANGE_USERNAME = 'ChangeUsername';

    const CLASS_MAP = [
        self::REGISTER_USER => RegisterUser::class,
        self::CHANGE_USERNAME => ChangeUsername::class,
    ];

    public static function createFromNameAndPayload(string $commandName, array $payload)
    {
        $class = self::CLASS_MAP[$commandName];

        return new $class($payload);
    }

    public static function nameOf($command): string
    {
        $map = array_flip(self::CLASS_MAP);
        return $map[get_class($command)];
    }

    private function __construct()
    {
        //static class only
    }
}
