<?php

namespace SV\SubscriberRemoved\Service\User;

use XF\Entity\User;
use XF\Entity\UserUpgradeActive;
use XF\Mvc\Entity\AbstractCollection;
use XF\Service\AbstractService;

class NotifyRemovedSubscriber extends AbstractService
{
    protected $action;

    protected $startThread = false;
    /** @var \XF\Entity\Forum */
    protected $threadForum = null;
    /** @var User */
    protected $threadAuthor = null;

    protected $startConversation = false;
    /** @var User */
    protected $conversationStarter = null;
    /** @var AbstractCollection */
    protected $conversationRecipients;

    /** @var null|\XF\Entity\User */
    protected $removedSubscriber = null;

    protected $isSubscriber = null;

    /** @var  \XF\Entity\UserUpgradeActive[] */
    protected $activeUpgrades = null;

    protected $contentPhrases   = [];
    protected $upgradePhrases   = [];
    protected $threadData       = [];
    protected $conversationData = [];

    public function __construct(\XF\App $app, User $removedSubscriber, $action)
    {
        $this->action = $action;
        $this->removedSubscriber = $removedSubscriber;

        parent::__construct($app);
    }

    protected function setup()
    {
        $this->startThread = \XF::options()->sv_subscriberremoved_thread_data['enable'];
        $this->startConversation = \XF::options()->sv_subscriberremoved_conversation_data['enable'];

        if ($this->startThread)
        {
            $this->setThreadData(\XF::options()->sv_subscriberremoved_thread_data);
        }

        if ($this->startConversation)
        {
            $this->setConversationData(\XF::options()->sv_subscriberremoved_conversation_data);
        }

        if ($this->isSubscriber === null || $this->activeUpgrades === null)
        {
            $this->isSubscriber = $this->determineIfSubscriber();
        }
    }

    protected function determineIfSubscriber()
    {
        /** @var \XF\Repository\UserUpgrade $userUpgradeRepo */
        $userUpgradeRepo = $this->repository('XF:UserUpgrade');
        $this->activeUpgrades = $userUpgradeRepo->findActiveUserUpgradesForList()
                                                ->where('user_id', $this->removedSubscriber->user_id)->fetch();

        $this->isSubscriber = $this->activeUpgrades->count() > 0;
    }

    protected function setThreadData(array $threadData)
    {
        $this->threadData = $threadData;
        $this->threadForum = $this->findOne('XF:Forum', ['node_id' => $threadData['nodeId']]);
        $this->threadAuthor = $this->findOne('XF:User', ['user_id' => $threadData['threadAuthorId']]);
    }

    protected function setConversationData(array $conversationData)
    {
        $this->conversationData = $conversationData;
        $this->conversationStarter = $this->findOne('XF:User', ['user_id' => $conversationData['starterId']]);
        $this->conversationRecipients = $this->finder('XF:User')->whereIds($conversationData['recipientIds'])->fetch();
    }

    protected function getUpgradePhrases()
    {
        if (!$this->upgradePhrases)
        {
            $this->generateUpgradePhrases();
        }

        return $this->upgradePhrases;
    }

    protected function generateUpgradePhrases()
    {
        foreach ($this->activeUpgrades AS $activeUpgrade)
        {
            $upgradeParams = $this->getUpgradePhraseParams($activeUpgrade);

            $this->upgradePhrases[] = \XF::phrase('sv_subscriberremoved_thread_message_upgrade', $upgradeParams)
                                         ->render();
        }
    }

    protected function getThreadTitle()
    {
        return \XF::phrase('sv_subscriberremoved_title', $this->getPhraseParams())->render();
    }

    protected function getThreadMessage()
    {
        return \XF::phrase('sv_subscriberremoved_message', $this->getPhraseParams())->render();
    }

    protected function getConversationTitle()
    {
        return $this->getThreadTitle();
    }

    protected function getConversationMessage()
    {
        return $this->getThreadMessage();
    }

    protected function getUpgradePhraseParams(UserUpgradeActive $activeUpgrade)
    {
        /** @var \XF\Entity\PaymentProviderLog $paymentLog */
        $paymentLog = $this->finder('XF:PaymentProviderLog')
                           ->where('purchase_request_key', $activeUpgrade->purchase_request_key)
                           ->fetchOne();
        $txnId = $paymentLog ? $paymentLog->transaction_id : \XF::phrase('n_a');

        return [
            'title'           => $activeUpgrade->Upgrade->title,
            'cost_phrase'     => $activeUpgrade->Upgrade->cost_phrase,
            'length_amount'   => $activeUpgrade->Upgrade->length_amount,
            'length_unit'     => $activeUpgrade->Upgrade->length_unit,
            'payment_profile' => $activeUpgrade->PurchaseRequest ? $activeUpgrade->PurchaseRequest->PaymentProfile->title : \XF::phrase('manually_upgrade_user'),
            'txnId'           => $txnId
        ];
    }

    protected function getPhraseParams()
    {
        return [
            'removedUserName'  => $this->removedSubscriber->username,
            'removedUserEmail' => $this->removedSubscriber->email,
            'removedUserUrl'   => \XF::app()->router('public')->buildLink('members', $this->removedSubscriber),
            'removedUserId'    => $this->removedSubscriber->user_id,
            'action'           => $this->action,
            'upgrades'         => implode("\n", $this->getUpgradePhrases())
        ];
    }

    public function notify()
    {
        if ($this->startThread)
        {
            if ($this->threadForum && $this->threadAuthor)
            {
                /** @var \XF\Service\Thread\Creator $threadCreator */
                $threadCreator = \XF::asVisitor($this->threadAuthor, function () {
                    /** @var \XF\Service\Thread\Creator $threadCreator */
                    $threadCreator = $this->service('XF:Thread\Creator', $this->threadForum);
                    $threadCreator->setContent($this->getThreadTitle(), $this->getThreadMessage());
                    $threadCreator->setIsAutomated();
                    $threadCreator->setPrefix($this->threadForum->default_prefix_id);
                    $threadCreator->save();

                    return $threadCreator;
                });
                $threadCreator->sendNotifications();
            }
            else
            {
                if (!$this->threadForum)
                {
                    \XF::logError("Expected user {$this->threadForum['threadAuthorId']} to exist when reporting " . $this->getThreadTitle(), true);
                }
                if (!$this->threadAuthor)
                {
                    \XF::logError("Expected forum {$this->threadForum['nodeId']} to exist when reporting " . $this->getThreadTitle(), true);
                }
            }
        }

        if ($this->startConversation)
        {
            if ($this->conversationStarter && $this->conversationRecipients->count() > 0)
            {
                /** @var \XF\Service\Conversation\Creator $conversationCreator */
                $conversationCreator = \XF::asVisitor($this->conversationStarter, function () {
                    /** @var \XF\Service\Conversation\Creator $conversationCreator */
                    $conversationCreator = $this->service('XF:Conversation\Creator', $this->conversationStarter);
                    $conversationCreator->setRecipientsTrusted($this->conversationRecipients);
                    $conversationCreator->setContent($this->getConversationTitle(), $this->getConversationMessage());
                    $conversationCreator->setIsAutomated();
                    $conversationCreator->save();

                    return $conversationCreator;
                });
                $conversationCreator->sendNotifications();
            }
            else
            {
                \XF::logError("Expected user {$this->conversationData['starterId']} to exist when reporting " . $this->getConversationTitle(), true);
            }
        }
    }
}
