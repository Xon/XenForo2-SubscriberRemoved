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
            $this->notifySubscriberRemoved('banned');
        }
        else if ($this->isChanged('user_state')
                 && in_array($this->user_state, ['rejected', 'disabled'], true)
                 && in_array($this->getPreviousValue('user_state'), ['rejected', 'disabled'], true))
        {
            $this->notifySubscriberRemoved($this->user_state);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        if (!$this->is_banned && !in_array($this->user_state, ['rejected', 'disabled'], true))
        {
            $this->notifySubscriberRemoved('deleted');
        }
    }

    protected function notifySubscriberRemoved(string $action): void
    {
        \XF::runLater(function () use ($action) {
            $service = NotifyRemovedSubscriberService::get($this, $action);
            $service->notify();
        });
    }
}
