<?php

namespace SV\SubscriberRemoved\Option;

use SV\StandardLib\Helper;
use XF\Entity\Option as OptionEntity;
use XF\Entity\User as UserEntity;
use XF\Finder\User as UserFinder;
use XF\Option\AbstractOption;
use XF\Repository\Node as NodeRepo;
use XF\Repository\User as UserRepo;
use function array_map;
use function reset;

class SubscriberRemovedCreateThread extends AbstractOption
{
    public static function renderOption(OptionEntity $option, array $htmlParams): string
    {
        $selectData = self::getSelectData($option, $htmlParams);

        $select = self::getTemplater()->formSelect(
            $selectData['controlOptions'], $selectData['choices']
        );

        $threadData = $option->option_value;
        $threadAuthor = (int)($threadData['threadAuthorId'] ?? 0);
        if ($threadAuthor !== 0)
        {
            $rawUsernames = Helper::finder(UserFinder::class)
                                  ->where('user_id', $threadAuthor)
                                  ->limit(1)
                                  ->fetchColumns('username');
            $rawUsernames = reset($rawUsernames);
            $threadAuthor = $rawUsernames ? $rawUsernames['username'] : null;
        }

        return self::getTemplate(
            'admin:svSubscriberRemoved_option_template_thread', $option, $htmlParams, [
                'threadAuthor' => $threadAuthor,
                'nodeSelect'   => $select
            ]
        );
    }

    public static function verifyOption(array &$threadData, OptionEntity $option): bool
    {
        if (isset($threadData['enable']))
        {
            $threadData['enable'] = (int)$threadData['enable'];
            $threadData['nodeId'] = (int)$threadData['nodeId'];

            $userRepo = Helper::repository(UserRepo::class);
            $threadAuthorId = (int)($threadData['threadAuthor'] ?? 0);
            unset($threadData['threadAuthor']);
            if ($threadAuthorId !== 0)
            {
                /** @var UserEntity|null $threadAuthor */
                $threadAuthor = $userRepo->getUserByNameOrEmail($threadAuthorId);
                if ($threadAuthor === null)
                {
                    $option->error(\XF::phrase('requested_user_x_not_found', ['name' => $threadData['starter']]));
                }
                else
                {
                    $threadData['threadAuthorId'] = $threadAuthor->user_id;
                }
            }
        }

        return !$option->hasErrors();
    }

    protected static function getSelectData(OptionEntity $option, array $htmlParams): array
    {
        $nodeRepo = Helper::repository(NodeRepo::class);

        $choices = $nodeRepo->getNodeOptionsData(true, 'Forum', 'option');
        $choices = array_map(
            function ($v) {
                $v['label'] = \XF::escapeString($v['label']);

                return $v;
            }, $choices
        );

        return [
            'choices'        => $choices,
            'controlOptions' => [
                'name'  => $htmlParams['inputName'] . '[nodeId]',
                'value' => $option->option_value['nodeId']
            ]
        ];
    }
}