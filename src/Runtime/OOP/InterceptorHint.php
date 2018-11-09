<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Runtime\OOP;

use Prooph\EventMachine\Runtime\OOPAggregateCallInterceptor;

final class InterceptorHint
{
    public static function useAggregate()
    {
        throw new \BadMethodCallException(__METHOD__  . ' should never be called. Check that EventMachine uses ' . OOPAggregateCallInterceptor::class);
    }
}
