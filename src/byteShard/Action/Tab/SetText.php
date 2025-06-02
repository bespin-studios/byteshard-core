<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Tab;

use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

class SetText extends Action
{
    private string $tabName;
    private string $text;

    public function __construct(string $tabName, string $text = '')
    {
        $this->tabName = $tabName;
        $this->text    = $text;
    }

    protected function runAction(): ActionResultInterface
    {
        $ids = $_SESSION[MAIN]->getIDByFQCN($this->tabName);
        foreach ($ids as $tabId) {
            $action['tab'][$tabId]['setText'] = $this->text;
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }
}