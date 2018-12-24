<?php

namespace SV\SubscriberRemoved\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;

class SubscriberRemovedStartConversation extends AbstractOption
{
    public static function renderOption(Option $option, array $htmlParams)
    {
        $conversationData = $option->option_value;
        $starter = empty($conversationData['starterId']) ? null : $conversationData['starterId'];
        if ($starter)
        {
            $rawUsernames = \XF::finder('XF:User')
                               ->where('user_id', $starter)
                               ->limit(1)
                               ->fetchColumns('username');
            $rawUsernames = reset($rawUsernames);
            $starter = $rawUsernames ? $rawUsernames['username'] : null;
        }

        $recipients = empty($conversationData['recipientIds']) ? [] : $conversationData['recipientIds'];
        $recipientUsers = [];
        if ($recipients)
        {
            $rawUsers = \XF::finder('XF:User')
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
                'recipients'  => implode(", ", $recipientUsers),
            ]
        );
    }

    public static function verifyOption(array &$conversationData, Option $option)
    {
        if (isset($conversationData['enable']))
        {
            $conversationData['enable'] = (int)$conversationData['enable'];

            /** @var \XF\Repository\User $userRepo */
            $userRepo = \XF::repository('XF:User');


            if (isset($conversationData['starter']))
            {
                $starter = $conversationData['starter'];
                /** @var \XF\Entity\User $conversationStarter */
                $conversationStarter = $userRepo->getUserByNameOrEmail($starter);
                unset($conversationData['starter']);

                if (!$conversationStarter)
                {
                    $option->error(\XF::phrase('requested_user_x_not_found', ['name' => $starter]));
                }
                else
                {
                    $conversationData['starterId'] = $conversationStarter->user_id;
                }
            }

            if (isset($conversationData['recipients']))
            {
                $recipients = preg_split('#\s*,\s*#', $conversationData['recipients'], -1, PREG_SPLIT_NO_EMPTY);
                unset($conversationData['recipients']);
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

                $conversationData['recipientIds'] = \array_keys($matchedUsers->toArray());
            }
        }

        return !$option->hasErrors();
    }
}
