<?php

namespace SV\SubscriberRemoved\Service\User;

use SV\StandardLib\Helper;
use XF\App;
use XF\Entity\Forum as ForumEntity;
use XF\Entity\User as UserEntity;
use XF\Entity\UserUpgradeActive as UserUpgradeActiveEntity;
use XF\Finder\PaymentProviderLog as PaymentProviderLogFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\UserUpgrade as UserUpgradeRepo;
use XF\Service\AbstractService;
use XF\Service\Conversation\Creator as ConversationCreatorService;
use XF\Service\Thread\Creator as ThreadCreatorService;
use function implode;

class NotifyRemovedSubscriber extends AbstractService
{
    /** @var string */
    protected $action;

    /** @var bool */
    protected $startThread = false;
    /** @var ForumEntity */
    protected $threadForum = null;
    /** @var UserEntity */
    protected $threadAuthor = null;

    /** @var bool */
    protected $startConversation = false;
    /** @var UserEntity */
    protected $conversationStarter = null;
    /** @var AbstractCollection<UserEntity> */
    protected $conversationRecipients;

    /** @var null|UserEntity */
    protected $removedSubscriber = null;

    /** @var null|bool */
    protected $isSubscriber = null;

    /** @var  UserUpgradeActiveEntity[] */
    protected $activeUpgrades = null;

    protected $contentPhrases   = [];
    protected $upgradePhrases   = [];
    protected $threadData       = [];
    protected $conversationData = [];

    public static function get(UserEntity $removedSubscriber, string $action): self
    {
        return Helper::service(self::class, $removedSubscriber, $action);
    }

    public function __construct(App $app, UserEntity $removedSubscriber, string $action)
    {
        $this->action = $action;
        $this->removedSubscriber = $removedSubscriber;

        parent::__construct($app);
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setup(): void
    {
        $options = \XF::options();

        $threadData = $options->sv_subscriberremoved_thread_data;
        $this->startThread = !empty($threadData['enable']);
        if ($this->startThread)
        {
            $this->setThreadData($threadData);
        }

        $convData = $options->sv_subscriberremoved_conversation_data;
        $this->startConversation = !empty($convData['enable']);
        if ($this->startConversation)
        {
            $this->setConversationData($convData);
        }

        if ($this->isSubscriber === null || $this->activeUpgrades === null)
        {
            $this->determineIfSubscriber();
        }
    }

    protected function determineIfSubscriber(): void
    {
        $userUpgradeRepo = \XF::repository(UserUpgradeRepo::class);
        $this->activeUpgrades = $userUpgradeRepo->findActiveUserUpgradesForList()
                                                ->where('user_id', $this->removedSubscriber->user_id)
                                                ->fetch();

        $this->isSubscriber = $this->activeUpgrades->count() > 0;
    }

    protected function setThreadData(array $threadData): void
    {
        $this->threadData = $threadData;
        $this->threadForum = Helper::findOne(ForumEntity::class, ['node_id' => $threadData['nodeId']]);
        $this->threadAuthor = Helper::findOne(UserEntity::class, ['user_id' => $threadData['threadAuthorId']]);
    }

    protected function setConversationData(array $conversationData): void
    {
        $this->conversationData = $conversationData;
        $this->conversationStarter = Helper::findOne(UserEntity::class, ['user_id' => $conversationData['starterId']]);
        $this->conversationRecipients = Helper::findByIds(UserEntity::class, $conversationData['recipientIds']);
    }

    protected function getUpgradePhrases(): array
    {
        if (!$this->upgradePhrases)
        {
            $this->generateUpgradePhrases();
        }

        return $this->upgradePhrases;
    }

    protected function generateUpgradePhrases(): void
    {
        foreach ($this->activeUpgrades AS $activeUpgrade)
        {
            $upgradeParams = $this->getUpgradePhraseParams($activeUpgrade);

            $this->upgradePhrases[] = \XF::phrase('sv_subscriberremoved_thread_message_upgrade', $upgradeParams)
                                         ->render();
        }
    }

    protected function getThreadTitle(): string
    {
        return \XF::phrase('sv_subscriberremoved_title', $this->getPhraseParams())->render();
    }

    protected function getThreadMessage(): string
    {
        return \XF::phrase('sv_subscriberremoved_message', $this->getPhraseParams())->render();
    }

    protected function getConversationTitle(): string
    {
        return $this->getThreadTitle();
    }

    protected function getConversationMessage(): string
    {
        return $this->getThreadMessage();
    }

    protected function getUpgradePhraseParams(UserUpgradeActiveEntity $activeUpgrade): array
    {
        $paymentLog = Helper::finder(PaymentProviderLogFinder::class)
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

    protected function getPhraseParams(): array
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

    public function notify(): void
    {
        if (!$this->isSubscriber)
        {
            return;
        }

        if ($this->startThread)
        {
            if ($this->threadForum && $this->threadAuthor)
            {
                /** @var ThreadCreatorService $threadCreator */
                $threadCreator = \XF::asVisitor($this->threadAuthor, function () {
                    $threadCreator = Helper::service(ThreadCreatorService::class, $this->threadForum);
                    $threadCreator->setContent($this->getThreadTitle(), $this->getThreadMessage());
                    $threadCreator->setIsAutomated();
                    $forum = $this->threadForum;
                    $defaultPrefix = $forum->sv_default_prefix_ids ?? $forum->default_prefix_id;
                    if ($defaultPrefix)
                    {
                        $threadCreator->setPrefix($defaultPrefix);
                    }
                    $threadCreator->save();

                    return $threadCreator;
                });
                $threadCreator->sendNotifications();
            }
            else
            {
                if (!$this->threadForum)
                {
                    \XF::logError("Expected user {$this->threadData['threadAuthorId']} to exist when reporting " . $this->getThreadTitle(), true);
                }
                if (!$this->threadAuthor)
                {
                    \XF::logError("Expected forum {$this->threadData['nodeId']} to exist when reporting " . $this->getThreadTitle(), true);
                }
            }
        }

        if ($this->startConversation)
        {
            if ($this->conversationStarter && $this->conversationRecipients->count() > 0)
            {
                /** @var ConversationCreatorService $conversationCreator */
                $conversationCreator = \XF::asVisitor($this->conversationStarter, function () {
                    $conversationCreator = Helper::service(ConversationCreatorService::class, $this->conversationStarter);
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
