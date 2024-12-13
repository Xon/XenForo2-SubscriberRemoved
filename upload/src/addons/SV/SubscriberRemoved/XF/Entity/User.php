<?php

namespace SV\SubscriberRemoved\XF\Entity;

use SV\SubscriberRemoved\Service\User\NotifyRemovedSubscriber as NotifyRemovedSubscriberService;

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
