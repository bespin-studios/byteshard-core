<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Exception;
use byteShard\ID\ID;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\CellContent;
use byteShard\Internal\Debug;
use byteShard\Layout;
use byteShard\Locale;
use byteShard\Popup;
use byteShard\Popup\Message;
use byteShard\Session;
use byteShard\Settings;
use byteShard\TabNew;

/**
 * Class OpenPopup
 * @package byteShard\Action
 */
class OpenPopup extends Action
{
    /** @var Popup[] */
    private array $popups = [];

    /**
     * OpenPopup constructor.
     * @param Popup ...$popups
     * @throws Exception
     */
    public function __construct(Popup ...$popups)
    {
        foreach ($popups as $popup) {
            $this->pushPopupToArray($popup);
        }
    }

    private function pushPopupToArray(Popup $popup): void
    {
        $className = strtolower(get_class($popup));
        if (str_starts_with($className, 'app\\popup\\')) {
            $this->popups[$className] = $popup;
        } else {
            throw new Exception("Popup class $className does not start with app\\popup\\");
        }
    }

    /**
     * @param Popup $popup
     * @return $this
     * @API
     * @throws Exception
     */
    public function addPopup(Popup $popup): self
    {
        $this->pushPopupToArray($popup);
        return $this;
    }

    private function initializePopups(TabIDElement $tabIDElement): void
    {
        foreach ($this->popups as $popup) {
            $popup->addTabIdElement($tabIDElement);
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $action['state']    = HttpResponseState::ERROR->value;
        $failedHeight       = 200;
        $failedWidth        = 400;
        $noConditionMessage = '';
        $conditionsMet      = true;
        $mergeArray         = [];
        if ($this->getActionInitDTO()->eventContainer instanceof CellContent) {
            $containerId = $this->getActionInitDTO()->eventContainer->getNewId();
        } elseif ($this->getActionInitDTO()->eventContainer instanceof TabNew) {
            $containerId = $this->getActionInitDTO()->eventContainer->getId();
        }
        if (isset($containerId) && $containerId instanceof ID) {
            $tabIdElement = new TabIDElement($containerId->getTabId());
            $this->initializePopups($tabIdElement);
        }
        // cycle through all popups and check if its conditions are met, if false, break and display noConditionMessage
        foreach ($this->popups as $popup) {
            $popup->definePopup();
            $content = $popup->getContent();
            if ($content instanceof Layout) {
                $id = clone $popup->getNewId();
                $content->setContentContainerId($id);
            }

            $conditions = $popup->conditionsMet();
            if ($conditions['state'] === true) {

                if (Settings::logTabChangeAndPopup() === true) {
                    Debug::notice('[Popup] '.$popup->getName());
                }
                $mergeArray[] = $popup->getNavigationArray();
                $getData      = $this->getActionInitDTO()->getData;
                if ($getData !== null && $content instanceof Layout) {
                    $cells = $content->getCells();
                    foreach ($cells as $layoutCell) {
                        $cell = Session::getCell($layoutCell->getId());
                        if ($cell instanceof Cell) {
                            $cell->setGetDataActionClientData($getData);
                        }
                    }
                }
            } else {
                $conditionsMet      = false;
                $noConditionMessage = $conditions['text'];
                $failedHeight       = $conditions['height'];
                $failedWidth        = $conditions['width'];
                break;
            }
        }
        if ($conditionsMet === true) {
            $action[Action\ActionTargetEnum::Popup->value] = array_merge_recursive(...$mergeArray);
            $action['state']                               = HttpResponseState::SUCCESS->value;
        } else {
            $msg = new Message($noConditionMessage === '' ? Locale::get('action.generic') : $noConditionMessage, Popup\Enum\Message\Type::NOTICE);
            $msg->setHeight($failedHeight)->setWidth($failedWidth);
            $action = $msg->getNavigationArray();
        }
        return new Action\ActionResultMigrationHelper($action);
    }
}
