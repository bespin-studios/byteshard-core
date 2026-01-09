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
    private string $popupId;

    /**
     * ClosePopup constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        foreach ($cells as $cell) {
            $this->popups[strtolower($cell)] = $cell;
        }
    }

    public function setPopupId(string $popupId): self
    {
        $this->popupId = $popupId;
        return $this;
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
            if (empty($this->popups)) {
                if (!isset($this->popupId)) {
                    $cellId = $this->getActionInitDTO()->cell->getNewId();
                    if ($cellId->isPopupId()) {
                        $encryptedPopupId = ID::factory(new TabIDElement($cellId->getTabId()), new PopupIDElement($cellId->getPopupId()))->getEncryptedContainerId();
                        $action[Action\ActionTargetEnum::Popup->value][$encryptedPopupId]['close'] = true;
                    }
                }
            } else {
                foreach ($this->popups as $lowercase => $popup) {
                    $encryptedPopupId = '';
                    if (str_starts_with($lowercase, 'app\\cell') || str_starts_with($lowercase, '\\app\\cell') || !str_starts_with($lowercase, '\\')) {
                        $cells = $this->getCells([$popup]);
                        if (!empty($cells)) {
                            $cellId = $cells[0]->getNewId();
                            if ($cellId->isPopupId()) {
                                $encryptedPopupId = ID::factory(new TabIDElement($cellId->getTabId()), new PopupIDElement($cellId->getPopupId()))->getEncryptedContainerId();
                            }
                        }
                    } elseif (str_starts_with($lowercase, 'app\\popup') || str_starts_with($lowercase, '\\app\\popup')) {
                        $encryptedPopupId = ID::factory($tabIdElement, new PopupIDElement($popup))->getEncryptedContainerId();
                    }
                    if ($encryptedPopupId !== '') {
                        $action[Action\ActionTargetEnum::Popup->value][$encryptedPopupId]['close'] = true;
                    }
                }
            }
        }
        if (isset($this->popupId)) {
            $action[Action\ActionTargetEnum::Popup->value][$this->popupId]['close'] = true;
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }
}
