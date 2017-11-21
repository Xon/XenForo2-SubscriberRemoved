<?php

namespace SV\SubscriberRemoved\Option;

use XF\Option\AbstractOption;

class SubscriberRemovedStartConversation extends AbstractOption
{
    public static function verifyOption(array &$conversationData, \XF\Entity\Option $option)
    {
        if (isset($conversationData['start_conversation']))
        {
            $conversationStarter = \XF::repository('XF:User')->getUserByNameOrEmail($conversationData['starter']);

            if (!$conversationStarter)
            {
                $option->error(\XF::phrase('sv_subscriberremoved_invalid_conversation_starter'));

                return false;
            }

            $conversationData['starter'] = $conversationStarter->username;

            $recipients = preg_split('#\s*,\s*#', $conversationData['recipients'], -1, PREG_SPLIT_NO_EMPTY);

            foreach ($recipients AS $key => &$recipient)
            {
                $user = \XF::repository('XF:User')->getUserByNameOrEmail($recipient);

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
