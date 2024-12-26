<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Form\Control\Combo;
use byteShard\ID\DateIDElement;
use byteShard\ID\ID;
use byteShard\ID\IDElement;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Session;
use Exception;

/**
 * Class SetSelectedID
 * @package byteShard\Action
 */
class SetSelectedID extends Action
{
    private array $cells = [];
    private ?ID   $id;

    /**
     * SetSelectedID constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        parent::__construct();
        foreach ($cells as $cell) {
            $cellName               = Cell::getContentCellName($cell);
            $this->cells[$cellName] = $cellName;
        }
        $this->addUniqueID($this->cells);
    }

    /**
     * @param ID $id
     * @return $this
     * @API
     */
    public function setId(ID $id): self
    {
        $this->id = $id;
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $container = $this->getLegacyContainer();
        $id        = $this->getLegacyId();
        if ($container instanceof Cell) {
            $appendId   = false;
            $idElements = [];
            if (isset($this->id)) {
                $setId = $this->id;
            } else {
                $clientData = $this->getClientData();
                if (array_key_exists('!#SelectedSchedulerDate', $id)) {
                    $idElements[] = new DateIDElement($id['!#SelectedSchedulerDate']);
                } elseif (count($id) === 1 && array_key_exists('colID', $id) && property_exists($clientData, 'ID')) {
                    // this is the implementation for onSelect
                    // onGridLink doesn't work the same way as onSelect since we need a column and a row for the links instead of only a row
                    // refactor once there is a common implementation for all grid related events
                    try {
                        $decrypted = json_decode(Session::decrypt($clientData->ID), true);
                    } catch (Exception) {
                        $decrypted = [];
                    }
                    foreach ($decrypted as $clientObject => $clientValue) {
                        if (property_exists($clientData, $clientObject)) {
                            $idElements[] = new IDElement($clientObject, $clientData->{$clientObject});
                        }
                    }
                } else {
                    $actionId = $container->getActionId();
                    $row      = $clientData->getRows()[0];
                    if (property_exists($row, $actionId) && $row->{$actionId}->type === Combo::class) {
                        $idElements[] = new IDElement($actionId, $row->{$actionId}->value);
                        $appendId     = true;
                    } elseif (is_array($id) && array_key_exists('colID', $id) && array_key_exists('objID', $id)) {
                        // this is the implementation for onLinkClick in Image columns
                        $col = json_decode(Session::decrypt($id['colID']));
                        $obj = json_decode(Session::decrypt($id['objID']), true);
                        $idElements[] = new IDElement($col->i, $obj);
                    } else {
                        foreach ($id as $clientObject => $clientValue) {
                            if (property_exists($clientData, $clientObject)) {
                                $idElements[] = new IDElement($clientObject, $clientData->{$clientObject});
                            } else {
                                $decrypted = null;
                                try {
                                    $decrypted = json_decode(Session::decrypt($clientObject));
                                } catch (Exception) {
                                }
                                if ($decrypted !== null) {
                                    if (property_exists($clientData, $decrypted->i) && $clientData->{$decrypted->i} !== null) {
                                        $idElements[] = new IDElement($decrypted->i, $clientData->{$decrypted->i});
                                    }
                                }
                            }
                        }
                    }
                }
                $setId = ID::factory(...$idElements);
            }

            if (empty($this->cells)) {
                if ($appendId === true) {
                    $currentId = $container->getSelectedId();
                    if ($currentId === null) {
                        $container->setSelectedID($setId);
                    } else {
                        $currentId->addIdElement(...$idElements);
                    }
                } else {
                    $container->setSelectedID($setId);
                }
            } else {
                $cells = $this->getCells($this->cells);
                foreach ($cells as $cell) {
                    if ($appendId === true) {
                        $currentId = $cell->getSelectedId();
                        if ($currentId === null) {
                            $cell->setSelectedID($setId);
                        } else {
                            $currentId->addIdElement(...$idElements);
                        }
                    } else {
                        $cell->setSelectedID($setId);
                    }
                }
            }
        }
        return new Action\ActionResult();
    }
}
