<?php
declare(strict_types = 1);

namespace ProophExample\Aggregate;

final class CachableUserFunction
{
    public static function registerUser(array $registerUser) {
        //We just turn the command payload into event payload by yielding it
        yield $registerUser;
    }

    public static function whenUserWasRegistered(array $userWasRegistered) {
        $user = new UserState();
        $user->id = $userWasRegistered[CacheableUserDescription::IDENTIFIER];
        $user->username = $userWasRegistered['username'];
        $user->email = $userWasRegistered['email'];
        return $user;
    }

    public static function changeUsername(UserState $user, array $changeUsername) {
        yield [
            CacheableUserDescription::IDENTIFIER => $user->id,
            'oldName' => $user->username,
            'newName' => $changeUsername['username']
        ];
    }

    public static function whenUsernameWasChanged(UserState $user, array $usernameWasChanged) {
        $user->username = $usernameWasChanged['newName'];
        return $user;
    }

    private function __construct()
    {
        //static class only
    }
}
