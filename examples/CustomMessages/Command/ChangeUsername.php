<?php
declare(strict_types=1);

namespace ProophExample\CustomMessages\Command;

use ProophExample\CustomMessages\Util\ApplyPayload;

final class ChangeUsername
{
    use ApplyPayload;

    /**
     * @var string
     */
    public $userId;

    /**
     * @var string
     */
    public $username;
}
