<?php

namespace SV\SubscriberRemoved\XF\Entity;

class User extends XFCP_User
{
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->is_banned && $this->isChanged('is_banned'))
        {
            $this->app()->service('SV\SubscriberRemoved:User\NotifyRemovedSubscriber', $this, 'banned')->notify();
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        if (!$this->is_banned)
        {
            $this->app()->service('SV\SubscriberRemoved:User\NotifyRemovedSubscriber', $this, 'deleted')->notify();
        }
    }
}
