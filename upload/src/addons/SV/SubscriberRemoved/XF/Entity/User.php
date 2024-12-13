<?php

namespace SV\SubscriberRemoved\XF\Entity;

use SV\SubscriberRemoved\Service\User\NotifyRemovedSubscriber as NotifyRemovedSubscriberService;
use function in_array;

class User extends XFCP_User
{
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->is_banned && $this->isChanged('is_banned'))
        {
            \XF::runLater(function () {
                $service = NotifyRemovedSubscriberService::get($this, 'banned');
                $service->notify();
            });
        }
        else if ($this->isChanged('user_state')
                 && in_array($this->user_state, ['rejected', 'disabled'], true)
                 && in_array($this->getPreviousValue('user_state'), ['rejected', 'disabled'], true))
        {
            \XF::runLater(function () {
                $service = NotifyRemovedSubscriberService::get($this, $this->user_state);
                $service->notify();
            });
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        if (!$this->is_banned)
        {
            \XF::runLater(function () {
                $service = NotifyRemovedSubscriberService::get($this, 'banned');
                $service->notify();
            });
        }
    }
}
