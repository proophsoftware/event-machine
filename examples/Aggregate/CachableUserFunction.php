<?php
declare(strict_types = 1);

namespace ProophExample\Aggregate;

use Prooph\Common\Messaging\Message;
use ProophExample\Messaging\Event;

final class CachableUserFunction
{
    public static function registerUser(Message $registerUser) {
        //We just turn the command payload into event payload by yielding it
        yield [Event::USER_WAS_REGISTERED, $registerUser->payload()];
    }

    public static function whenUserWasRegistered(Message $userWasRegistered) {
        $user = new UserState();
        $user->id = $userWasRegistered->payload()[CacheableUserDescription::IDENTIFIER];
        $user->username = $userWasRegistered->payload()['username'];
        $user->email = $userWasRegistered->payload()['email'];
        return $user;
    }

    public static function changeUsername(UserState $user, Message $changeUsername) {
        yield [Event::USERNAME_WAS_CHANGED, [
            CacheableUserDescription::IDENTIFIER => $user->id,
            'oldName' => $user->username,
            'newName' => $changeUsername->payload()['username']
        ]];
    }

    public static function whenUsernameWasChanged(UserState $user, Message $usernameWasChanged) {
        $user->username = $usernameWasChanged->payload()['newName'];
        return $user;
    }

    private function __construct()
    {
        //static class only
    }
}
