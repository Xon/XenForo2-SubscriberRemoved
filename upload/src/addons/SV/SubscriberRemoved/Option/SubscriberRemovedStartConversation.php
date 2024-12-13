<?php

namespace SV\SubscriberRemoved\Option;

use SV\StandardLib\Helper;
use XF\Entity\Option as OptionEntity;
use XF\Entity\User as UserEntity;
use XF\Finder\User as UserFinder;
use XF\Option\AbstractOption;
use XF\Repository\User as UserRepo;
use function array_keys;
use function array_map;
use function implode;
use function preg_split;
use function reset;

class SubscriberRemovedStartConversation extends AbstractOption
{
    public static function renderOption(OptionEntity $option, array $htmlParams): string
    {
        $conversationData = $option->option_value;
        $starter = $conversationData['starterId'] ?? null;
        if ($starter !== null)
        {
            $rawUsernames = Helper::finder(UserFinder::class)
                                  ->where('user_id', $starter)
                                  ->limit(1)
                                  ->fetchColumns('username');
            $rawUsernames = reset($rawUsernames);
            $starter = $rawUsernames ? $rawUsernames['username'] : null;
        }

        $recipients = $conversationData['recipientIds'] ?? [];
        $recipientUsers = [];
        if ($recipients)
        {
            $rawUsers = Helper::finder(UserFinder::class)
                              ->where('user_id', $recipients)
                              ->fetchColumns('username');
            foreach ($rawUsers as $rawUser)
            {
                $recipientUsers[] = $rawUser['username'];
            }
        }

        return self::getTemplate(
            'admin:svSubscriberRemoved_option_template_convo', $option, $htmlParams, [
                'convStarter' => $starter,
                'recipients'  => implode(', ', $recipientUsers),
            ]
        );
    }

    public static function verifyOption(array &$conversationData, OptionEntity $option): bool
    {
        if (isset($conversationData['enable']))
        {
            $conversationData['enable'] = (int)$conversationData['enable'];

            $userRepo = Helper::repository(UserRepo::class);

            $starter = $conversationData['starter'] ?? null;
            unset($conversationData['starter']);
            if ($starter !== null)
            {
                /** @var UserEntity|null $conversationStarter */
                $conversationStarter = $userRepo->getUserByNameOrEmail($starter);
                if ($conversationStarter === null)
                {
                    $option->error(\XF::phrase('requested_user_x_not_found', ['name' => $starter]));
                }
                else
                {
                    $conversationData['starterId'] = $conversationStarter->user_id;
                }
            }

            $recipients = $conversationData['recipients'] ?? null;
            unset($conversationData['recipients']);
            if ($recipients !== null)
            {
                $recipients = preg_split('#\s*,\s*#', $recipients, -1, PREG_SPLIT_NO_EMPTY);
                $recipients = array_map('\trim', $recipients);
                $notFound = [];

                $matchedUsers = $userRepo->getUsersByNames($recipients, $notFound);
                if ($notFound)
                {
                    $option->error(\XF::phrase('following_members_not_found_x', ['members' => implode(', ', $notFound)]));
                }
                else if (!$recipients)
                {
                    $option->error(\XF::phrase('please_enter_at_least_one_valid_recipient'));
                }

                $conversationData['recipientIds'] = array_keys($matchedUsers->toArray());
            }
        }

        return !$option->hasErrors();
    }
}
