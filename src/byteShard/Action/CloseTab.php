<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

/**
 * Class CloseTab
 * @package byteShard\Action
 */
class CloseTab extends Action
{
    /**
     * CloseTab constructor.
     * @param string $className
     * @param int $id
     */
    public function __construct(private string $className, private int $id)
    {
    }

    protected function runAction(): ActionResultInterface
    {
        // 'removeTab'
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }
}
