<?php

namespace SV\SubscriberRemoved;

use SV\StandardLib\Helper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Entity\Option as OptionEntity;
use XF\Entity\User as UserEntity;
use XF\Finder\User as UserFinder;
use XF\Repository\User as UserRepo;
use function array_map;
use function is_array;
use function preg_split;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function upgrade2000000Step1(): void
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
            $recipients = array_map('\trim', $recipients);
            $rawUsers = Helper::finder(UserFinder::class)
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

            $this->app->registry()->set('svSubscriberRemovedOpts', $deferOptions);
        }
    }

    protected function finaliseOptions(): void
    {
        $deferOptions = $this->app->registry()->get('svSubscriberRemovedOpts');
        if (is_array($deferOptions))
        {
            foreach ($deferOptions as $optionName => $optionValue)
            {
                $option = Helper::find(OptionEntity::class, $optionName);
                if ($option !== null)
                {
                    $option->setOption('verify_value', false);
                    $option->setOption('verify_validation_callback', false);
                    $option->sub_options = ['*'];
                    $option->option_value = $optionValue;
                    $option->saveIfChanged();
                }
            }
        }
        $this->app->registry()->delete('svSubscriberRemovedOpts');
    }

    public function upgrade2010100Step1(): void
    {
        $userRepo = Helper::repository(UserRepo::class);

        $option = Helper::find(OptionEntity::class, 'sv_subscriberremoved_thread_data');
        if ($option !== null)
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
                /** @var UserEntity $threadAuthor */
                $threadAuthor = $userRepo->getUserByNameOrEmail($threadData['thread_author']);
                unset($threadData['thread_author']);

                if ($threadAuthor)
                {
                    $threadData['threadAuthorId'] = $threadAuthor->user_id;
                }
            }

            $option->setOption('verify_value', false);
            $option->setOption('verify_validation_callback', false);
            $option->sub_options = ['*'];
            $option->option_value = $threadData;
            $option->save();
        }

        $option = Helper::find(OptionEntity::class, 'sv_subscriberremoved_conversation_data');
        if ($option !== null)
        {
            $conversationData = $option->option_value;
            if (isset($conversationData['start_conversation']))
            {
                $conversationData['enable'] = (int)$conversationData['start_conversation'];
                unset($conversationData['start_conversation']);
            }

            if (isset($conversationData['starter']))
            {
                $threadAuthor = $userRepo->getUserByNameOrEmail($conversationData['starter']);
                unset($conversationData['starter']);
                if ($threadAuthor !== null)
                {
                    $conversationData['starterId'] = $threadAuthor->user_id;
                }
            }

            if (isset($conversationData['recipients']))
            {
                $recipients = preg_split('#\s*,\s*#', $conversationData['recipients'], -1, PREG_SPLIT_NO_EMPTY);
                unset($conversationData['recipients']);
                $recipientIds = [];

                foreach ($recipients as $recipient)
                {
                    $user = $userRepo->getUserByNameOrEmail($recipient);
                    if ($user === null)
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
            $option->sub_options = ['*'];
            $option->option_value = $conversationData;
            $option->save();
        }
    }


    public function postInstall(array &$stateChanges): void
    {
        parent::postInstall($stateChanges);
        $this->finaliseOptions();
    }

    public function postUpgrade($previousVersion, array &$stateChanges): void
    {
        $previousVersion = (int)$previousVersion;
        parent::postUpgrade($previousVersion, $stateChanges);
        $this->finaliseOptions();
    }

    public function uninstallStep1(): void
    {
        $this->app->registry()->delete('svSubscriberRemovedOpts');
    }
}
