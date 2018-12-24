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
                'create_thread' => $options->subnotify_createthread,
                'node_id'       => $options->subnotify_forumid,
                'thread_author' => $options->subnotify_username,
            ];
        }

        if ($options->offsetExists('subnotify_sendpm'))
        {
            $deferOptions['sv_subscriberremoved_conversation_data'] = [
                'start_conversation' => $options->subnotify_sendpm,
                'starter'            => $options->subnotify_pmusername,
                'recipients'         => $options->subnotify_pmrecipients,
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
        if (!$deferOptions)
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
