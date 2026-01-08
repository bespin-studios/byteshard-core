<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\ID\ID;
use byteShard\ID\PopupIDElement;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\CellContent;
use byteShard\TabNew;

/**
 * Class ClosePopup
 * @package byteShard\Action
 */
class ClosePopup extends Action
{
    private array $popups;

    /**
     * ClosePopup constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        $this->popups = parent::getUniqueCellNameArray(...$cells);
    }

    protected function runAction(): ActionResultInterface
    {
        if ($this->getActionInitDTO()->eventContainer instanceof CellContent) {
            $containerId = $this->getActionInitDTO()->eventContainer->getNewId();
        } elseif ($this->getActionInitDTO()->eventContainer instanceof TabNew) {
            $containerId = $this->getActionInitDTO()->eventContainer->getId();
        }
        if (isset($containerId) && $containerId instanceof ID) {
            $tabIdElement = new TabIDElement($containerId->getTabId());
            foreach ($this->popups as $popup) {
                $encryptedPopupId = ID::factory($tabIdElement, new PopupIDElement($popup))->getEncryptedContainerId();
                
                $action[Action\ActionTargetEnum::Popup->value][$encryptedPopupId]['close'] = true;
            }
            $action['state'] = HttpResponseState::SUCCESS->value;
            return new Action\ActionResultMigrationHelper($action);
        }
        return new Action\ActionResult();
    }
}
