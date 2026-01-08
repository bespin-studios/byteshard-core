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
    private array $cells;
    private ?ID   $id;

    /**
     * SetSelectedID constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        $this->cells = parent::getUniqueCellNameArray(...$cells);
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
        $actionInitDTO = $this->getActionInitDTO();
        $appendId      = false;
        $idElements    = [];
        if (isset($this->id)) {
            $setId = $this->id;
        } else {
            $clientData = $actionInitDTO->clientData;
            $affectedId = [];
            switch ($actionInitDTO->eventType) {
                case 'onEmptyClick':
                    // scheduler
                    $idElements[] = new DateIDElement($affectedId['!#SelectedSchedulerDate']);
                    break;
                case 'onChange':
                    $idElements[] = new IDElement($actionInitDTO->eventId, $actionInitDTO->clientData->{$actionInitDTO->eventId});
                    $appendId     = true;
                    break;
                case 'onSelect':
                case 'onRowSelect':
                    $affectedId = json_decode(Session::decrypt($clientData->ID), true);
                    foreach ($affectedId as $id => $value) {
                        $idElements[] = new IDElement($id, $clientData->{$id});
                    }
                    break;
                case 'onGridLink':
                    $idElements[] = new IDElement($actionInitDTO->eventId, $actionInitDTO->clientData->{$actionInitDTO->eventId});
                    break;
            }
            $setId = ID::factory(...$idElements);
        }
        if (empty($this->cells)) {
            $cells = [$actionInitDTO->eventContainer];
        } else {
            $cells = $this->getCells($this->cells);
        }
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
        return new Action\ActionResult();
    }
}
