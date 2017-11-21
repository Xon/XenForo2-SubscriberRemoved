<?php

namespace SV\SubscriberRemoved\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;

class SubscriberRemovedStartConversation extends AbstractOption
{
    public static function verifyOption(array &$conversationData, Option $option)
    {
        if (isset($conversationData['start_conversation']))
        {
            /** @var \XF\Repository\User $userRepo */
            $userRepo = \XF::repository('XF:User');

            /** @var \XF\Entity\User $conversationStarter */
            $conversationStarter = $userRepo->getUserByNameOrEmail($conversationData['starter']);

            if (!$conversationStarter)
            {
                $option->error(\XF::phrase('sv_subscriberremoved_invalid_conversation_starter'));

                return false;
            }

            $conversationData['starter'] = $conversationStarter->username;

            $recipients = preg_split('#\s*,\s*#', $conversationData['recipients'], -1, PREG_SPLIT_NO_EMPTY);

            foreach ($recipients AS $key => &$recipient)
            {
                /** @var \XF\Entity\User $user */
                $user = $userRepo->getUserByNameOrEmail($recipient);

                if (!$user)
                {
                    $option->error(\XF::phrase('sv_subscriberremoved_recipient_x_not_found', ['name' => $recipient]));

                    return false;
                }

                $recipient = $user->username;

                if ($user->user_id == $conversationStarter->user_id)
                {
                    unset($recipients[$key]);
                }
            }

            if (!$recipients)
            {
                $option->error(\XF::phrase('sv_subscriberremoved_at_least_one_recipient_required'));

                return false;
            }

            $conversationData['recipients'] = implode(', ', $recipients);
        }

        return true;
    }
}
