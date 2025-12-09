<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Exception;
use byteShard\ID\CellIDElement;
use byteShard\ID\ID;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Debug;
use byteShard\Internal\Struct;
use byteShard\Layout;
use byteShard\Locale;
use byteShard\Popup;
use byteShard\Popup\Message;
use byteShard\Session;
use byteShard\Settings;
use byteShard\Tab;

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
     */
    public function __construct(Popup ...$popups)
    {
        parent::__construct();
        foreach ($popups as $popup) {
            $this->popups[$popup->getName()] = $popup;
        }
        $this->addUniqueID(array_keys($this->popups));
    }

    /**
     * @param Popup $popup
     * @return $this
     * @API
     */
    public function addPopup(Popup $popup): self
    {
        $this->popups[$popup->getName()] = $popup;
        $this->addUniqueID(array_keys($this->popups));
        return $this;
    }

    /**
     * @param Cell $cell
     * @throws Exception
     */
    protected function initThisActionInCell(Cell $cell): void
    {
        $cellId = $cell->getNewId();
        if ($cellId instanceof ID) {
            $popups       = $this->popups;
            $this->popups = [];
            foreach ($popups as $key => $popup) {
                if ($popup instanceof Popup) {
                    if (get_class($popup) !== Popup::class) {
                        $popupClass = get_class($popup);
                        $popup->addTabIdElement(new TabIDElement($cellId->getTabId()));
                        $this->popups[$popupClass] = $popup;
                    } else {
                        $popup->addTabAndCellIDElement(new TabIDElement($cellId->getTabId()), new CellIDElement($cellId->getCellId()));
                        // conveniently attach single cell in case the popup is empty
                        if ($popup->hasContentAttached() === false) {
                            $popup->addCell(new Cell());
                        } else {
                            // popup already has cells, those cells are lacking the tab id, inject it now
                            $popup->addIdElementToAllCells(new TabIDElement($cellId->getTabId()));
                        }
                        $this->popups[$key] = $popup->getEncodedId();
                        Session::addPopup($popup);
                    }
                } else {
                    $this->popups[$key] = $popup;
                }
            }
        }
    }

    /**
     * @param Tab $tab
     * @throws Exception
     */
    protected function initThisActionInTab(Tab $tab): void
    {
        // TODO: check popup on tab toolbar
        $popups       = $this->popups;
        $this->popups = [];
        foreach ($popups as $key => $popup) {
            $this->popups[$key] = $popup->generateID($tab);
            Session::addPopup($popup);
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $id                 = $this->getLegacyId();
        $action['state']    = HttpResponseState::ERROR->value;
        $failedHeight       = 200;
        $failedWidth        = 400;
        $noConditionMessage = '';
        $conditionsMet      = true;
        $mergeArray         = [];
        // cycle through all popups and check if its conditions are met, if false, break and display noConditionMessage
        foreach ($this->popups as $popupId) {
            $popup = null;
            if ($popupId instanceof Popup && get_class($popupId) !== Popup::class && str_starts_with(strtolower(get_class($popupId)), 'app\\popup\\')) {
                $popup = $popupId;
                $popup->definePopup();
                $content = $popup->getContent();
                if ($content instanceof Layout) {
                    $id = clone $popup->getNewId();
                    $content->setContentContainerId($id);
                }
            } elseif (is_string($popupId)) {
                // deprecated
                $popup = Session::getPopup($popupId);
            }
            if ($popup !== null) {
                $conditions = $popup->conditionsMet();
                if ($conditions['state'] === true) {
                    if ($id instanceof Struct\GetData) {
                        $cells = $popup->getCells();
                        foreach ($cells as $cell) {
                            if ($cell instanceof Cell) {
                                $cell->setGetDataActionClientData($id);
                            }
                        }
                    }
                    if (Settings::logTabChangeAndPopup() === true) {
                        Debug::notice('[Popup] '.$popup->getName());
                    }
                    $mergeArray[] = $popup->getNavigationArray();
                } else {
                    $conditionsMet      = false;
                    $noConditionMessage = $conditions['text'];
                    $failedHeight       = $conditions['height'];
                    $failedWidth        = $conditions['width'];
                    break;
                }
            }
        }
        if ($conditionsMet === true) {
            $action['popup'] = array_merge_recursive(...$mergeArray);
            $action['state'] = HttpResponseState::SUCCESS->value;
        } else {
            $msg = new Message($noConditionMessage === '' ? Locale::get('action.generic') : $noConditionMessage, Popup\Enum\Message\Type::NOTICE);
            $msg->setHeight($failedHeight)->setWidth($failedWidth);
            $action = $msg->getNavigationArray();
        }
        return new Action\ActionResultMigrationHelper($action);
    }
}
