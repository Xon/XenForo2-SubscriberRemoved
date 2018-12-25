<?php

namespace SV\SubscriberRemoved;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function upgrade2000000Step1()
    {
        $deferOptions = [];

        $options = \XF::options();
        if ($options->offsetExists('subnotify_createthread'))
        {
            $deferOptions['sv_subscriberremoved_thread_data'] = [
                'enable'         => $options->subnotify_createthread,
                'nodeId'         => $options->subnotify_forumid,
                'threadAuthorId' => $options->subnotify_userid,
            ];
        }

        if ($options->offsetExists('subnotify_sendpm'))
        {
            $recipients = preg_split('#\s*,\s*#', $options->subnotify_pmrecipients, -1, PREG_SPLIT_NO_EMPTY);
            $recipients = \array_map('\trim', $recipients);
            $rawUsers = \XF::finder('XF:User')
                           ->where('username', $recipients)
                           ->limit(1)
                           ->fetchColumns('user_id');
            $recipients = [];
            foreach ($rawUsers as $rawUser)
            {
                $recipients[] = $rawUser['user_id'];
            }

            $deferOptions['sv_subscriberremoved_conversation_data'] = [
                'enable'       => $options->subnotify_sendpm,
                'starterId'    => $options->subnotify_pmsenderid,
                'recipientIds' => $recipients,
            ];
        }

        if ($deferOptions)
        {
            $this->app->registry()->set('svSubscriberRemovedOptions', $deferOptions);
        }
    }

    protected function finaliseOptions()
    {
        $deferOptions = $this->app->registry()->get('svSubscriberRemovedOptions');
        if (!$deferOptions && is_array($deferOptions))
        {
            foreach ($deferOptions as $optionName => $optionValue)
            {
                /** @var \XF\Entity\Option $option */
                $option = \XF::finder('XF:Option')->whereId($optionName)->fetchOne();
                if ($option)
                {
                    $option->option_value = $optionValue;
                    $option->saveIfChanged();
                }
            }
        }
        $this->app->registry()->delete('svSubscriberRemovedOptions');
    }

    public function upgrade2010100Step1()
    {
        /** @var \XF\Repository\User $userRepo */
        $userRepo = \XF::repository('XF:User');

        /** @var \XF\Entity\Option $option */
        $option = \XF::finder('XF:Option')->whereId('sv_subscriberremoved_thread_data')->fetchOne();
        if ($option)
        {
            $threadData = $option->option_value;
            if (isset($threadData['create_thread']))
            {
                $threadData['enable'] = (int)$threadData['create_thread'];
                unset($threadData['create_thread']);
            }
            if (isset($threadData['node_id']))
            {
                $threadData['nodeId'] = (int)$threadData['node_id'];
                unset($threadData['node_id']);
            }

            if (isset($threadData['thread_author']))
            {
                /** @var \XF\Entity\User $threadAuthor */
                $threadAuthor = $userRepo->getUserByNameOrEmail($threadData['thread_author']);
                unset($threadData['thread_author']);

                if ($threadAuthor)
                {
                    $threadData['threadAuthorId'] = $threadAuthor->user_id;
                }
            }

            $option->setOption('verify_value', false);
            $option->setOption('verify_validation_callback', false);
            $option->option_value = $threadData;
            $option->save();
        }

        /** @var \XF\Entity\Option $option */
        $option = \XF::finder('XF:Option')->whereId('sv_subscriberremoved_conversation_data')->fetchOne();
        if ($option)
        {
            $conversationData = $option->option_value;
            if (isset($conversationData['start_conversation']))
            {
                $conversationData['enable'] = (int)$conversationData['start_conversation'];
                unset($conversationData['start_conversation']);
            }

            if (isset($conversationData['starter']))
            {
                /** @var \XF\Entity\User $threadAuthor */
                $threadAuthor = $userRepo->getUserByNameOrEmail($conversationData['starter']);
                unset($conversationData['starter']);

                if ($threadAuthor)
                {
                    $conversationData['starterId'] = $threadAuthor->user_id;
                }
            }

            if (isset($conversationData['recipients']))
            {
                $recipients = preg_split('#\s*,\s*#', $conversationData['recipients'], -1, PREG_SPLIT_NO_EMPTY);
                unset($conversationData['recipients']);
                $recipientIds = [];

                foreach ($recipients AS $key => $recipient)
                {
                    /** @var \XF\Entity\User $user */
                    $user = $userRepo->getUserByNameOrEmail($recipient);

                    if (!$user)
                    {
                        continue;
                    }

                    if (isset($conversationData['starterId']) && $user->user_id === $conversationData['starterId'])
                    {
                        continue;
                    }
                    $recipientIds[] = $user->user_id;
                }

                $conversationData['recipientIds'] = $recipientIds;
            }

            $option->setOption('verify_value', false);
            $option->setOption('verify_validation_callback', false);
            $option->option_value = $conversationData;
            $option->save();
        }
    }


    public function postInstall(array &$stateChanges)
    {
        $this->finaliseOptions();
    }

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        $this->finaliseOptions();
    }

    public function uninstallStep1()
    {
        $this->app->registry()->delete('svSubscriberRemovedOptions');
    }
}
