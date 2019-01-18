<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\FunctionalFlavour\Aggregate;

use ProophExample\FunctionalFlavour\Util\ApplyPayload;

class UserState
{
    use ApplyPayload;

    public $userId;
    public $username;
    public $email;
    public $failed;
}
