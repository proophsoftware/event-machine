<?php
declare(strict_types=1);

namespace ProophExample\CustomMessages\Event;

use ProophExample\CustomMessages\Util\ApplyPayload;

final class UsernameChanged
{
    use ApplyPayload;

    /**
     * @var string
     */
    public $userId;

    /**
     * @var string
     */
    public $oldName;

    public $newName;
}
