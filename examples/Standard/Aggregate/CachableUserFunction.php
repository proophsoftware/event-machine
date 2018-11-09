<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophExample\Standard\Aggregate;

use Prooph\Common\Messaging\Message;
use ProophExample\Standard\Messaging\Event;

final class CachableUserFunction
{
    public static function registerUser(Message $registerUser)
    {
        if (! array_key_exists('shouldFail', $registerUser->payload()) || ! $registerUser->payload()['shouldFail']) {
            //We just turn the command payload into event payload by yielding it
            yield [Event::USER_WAS_REGISTERED, $registerUser->payload()];
        } else {
            yield [Event::USER_REGISTRATION_FAILED, [
                CacheableUserDescription::IDENTIFIER => $registerUser->payload()[CacheableUserDescription::IDENTIFIER],
            ]];
        }
    }

    public static function whenUserWasRegistered(Message $userWasRegistered)
    {
        $user = new UserState();
        $user->id = $userWasRegistered->payload()[CacheableUserDescription::IDENTIFIER];
        $user->username = $userWasRegistered->payload()['username'];
        $user->email = $userWasRegistered->payload()['email'];

        return $user;
    }

    public static function whenUserRegistrationFailed(Message $userRegistrationFailed)
    {
        $user = new UserState();
        $user->failed = true;

        return $user;
    }

    public static function changeUsername(UserState $user, Message $changeUsername)
    {
        yield [Event::USERNAME_WAS_CHANGED, [
            CacheableUserDescription::IDENTIFIER => $user->id,
            'oldName' => $user->username,
            'newName' => $changeUsername->payload()['username'],
        ]];
    }

    public static function whenUsernameWasChanged(UserState $user, Message $usernameWasChanged)
    {
        $user->username = $usernameWasChanged->payload()['newName'];

        return $user;
    }

    public static function doNothing(UserState $user, Message $doNothing)
    {
        yield null;
    }

    private function __construct()
    {
        //static class only
    }
}
