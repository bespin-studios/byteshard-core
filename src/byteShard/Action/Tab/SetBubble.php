<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Tab;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\ID;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Session;
use byteShard\Tab;

class SetBubble extends Action
{
    private array $cells = [];

    public function __construct(string ...$cells)
    {
        parent::__construct();
        foreach ($cells as $cell) {
            $cellName               = Cell::getContentCellName($cell);
            $this->cells[$cellName] = $cellName;
        }
        $this->addUniqueID($this->cells);
    }

    protected function runAction(): ActionResultInterface
    {
        $tabIds = $this->getUniqueTabIds();
        foreach ($tabIds as $tabId) {
            $tab = Session::getTab($tabId);
            if ($tab instanceof Tab) {
                $bubbles = $tab->bubbles() ?? [];
                foreach ($bubbles as $encryptedTabId => $bubble) {
                    $action['tab'][$encryptedTabId]['setBubble'] = $bubble;
                }
            }
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }

    private function getUniqueTabIds(): array
    {
        $tabIds = [];
        $cells  = $this->getCells($this->cells);
        foreach ($cells as $cell) {
            $tabId = $cell->getNewId()?->getTabId();
            if ($tabId !== null) {
                if (str_contains($tabId, '\\')) {
                    $topLevelTab          = explode('\\', $tabId)[0];
                    $tabIds[$topLevelTab] = ID\ID::factory(new ID\TabIDElement($topLevelTab));
                } else {
                    $tabIds[$tabId] = ID\ID::factory(new ID\TabIDElement($tabId));
                }
            }
        }
        return $tabIds;
    }
}