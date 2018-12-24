<?php

namespace SV\SubscriberRemoved\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;

class SubscriberRemovedCreateThread extends AbstractOption
{
    public static function renderOption(Option $option, array $htmlParams)
    {
        $selectData = self::getSelectData($option, $htmlParams);

        $select = self::getTemplater()->formSelect(
            $selectData['controlOptions'], $selectData['choices']
        );

        $threadData = $option->option_value;
        $threadAuthor = empty($threadData['threadAuthorId']) ? null : $threadData['threadAuthorId'];

        if ($threadAuthor)
        {
            $rawUsernames = \XF::finder('XF:User')
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

    public static function verifyOption(array &$threadData, Option $option)
    {
        if (isset($threadData['enable']))
        {
            $threadData['enable'] = (int)$threadData['enable'];
            $threadData['nodeId'] = (int)$threadData['nodeId'];

            /** @var \XF\Repository\User $userRepo */
            $userRepo = \XF::repository('XF:User');

            if (isset($threadData['threadAuthor']))
            {
                /** @var \XF\Entity\User $threadAuthor */
                $threadAuthor = $userRepo->getUserByNameOrEmail($threadData['threadAuthor']);
                unset($threadData['threadAuthor']);

                if (!$threadAuthor)
                {
                    $option->error(\XF::phrase('requested_user_x_not_found', ['name' => $threadData['starter']]));
                }

                $threadData['threadAuthorId'] = $threadAuthor->user_id;
            }
        }

        return !$option->hasErrors();
    }

    protected static function getSelectData(Option $option, array $htmlParams)
    {
        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = \XF::repository('XF:Node');

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
