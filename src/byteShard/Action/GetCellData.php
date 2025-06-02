<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Exception;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

/**
 * Class GetCellData
 * @package byteShard\Action
 */
class GetCellData extends Action
{
    private array $sources = [];

    /**
     * @param string ...$cells
     * @return $this
     * @throws Exception
     * @API
     */
    public function fromMasterCheckboxInCell(string ...$cells): self
    {
        foreach ($cells as $cell) {
            $className = trim(Cell::getContentClassName($cell, 'Grid', __METHOD__), '\\');

            $this->sources[$className]['getCheckedRows'] = true;
        }
        $this->addUniqueID($this->sources);
        return $this;
    }

    /**
     * @param string ...$cells
     * @return $this
     * @throws Exception
     * @API
     */
    public function fromSelectedRow(string ...$cells): self
    {
        foreach ($cells as $cell) {
            $className = trim(Cell::getContentClassName($cell, 'Grid', __METHOD__), '\\');

            $this->sources[$className]['getSelectedRow'] = true;
        }
        $this->addUniqueID($this->sources);
        return $this;
    }

    /**
     * @param string ...$cells
     * @return $this
     * @throws Exception
     * @API
     */
    public function fromHighlightedRow(string ...$cells): self
    {
        foreach ($cells as $cell) {
            $className = trim(Cell::getContentClassName($cell, 'Grid', __METHOD__), '\\');

            $this->sources[$className]['getHighlightedRow'] = true;
        }
        $this->addUniqueID($this->sources);
        return $this;
    }

    /**
     * @param string ...$cells
     * @return $this
     * @throws Exception
     * @API
     */
    public function fromForm(string ...$cells): self
    {
        foreach ($cells as $cell) {
            $className = trim(Cell::getContentClassName($cell, 'Form', __METHOD__), '\\');

            $this->sources[$className]['getFormData'] = true;
        }
        $this->addUniqueID($this->sources);
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $container       = $this->getLegacyContainer();
        $action['state'] = HttpResponseState::SUCCESS->value;
        $getData         = $this->getGetData();
        if ($container instanceof Cell) {
            if ($getData === null) {
                // no callback executed yet, request cell data from clients
                $action = array_merge_recursive($this->requestClientData());
            } else {
                // !!! TODO !!! this is probably the sole reason for id by reference, check if getData can be injected in subsequent actions and the respective cell in a meaningful way
                // this is really bad and confusing design, fix asap
                //$id = $getData;
                $container->setGetDataActionClientData($getData);
                $this->setRunNested();
            }
        }
        return new Action\ActionResultMigrationHelper($action);
    }

    //TODO: error handling if $this->getNavigationID returns null -> class not defined

    private function requestClientData(): array
    {
        $action = [];
        $this->setRunNested(false);
        foreach ($this->sources as $cellName => $types) {
            $cells = $this->getCells([$cellName]);
            foreach ($cells as $cell) {
                foreach ($types as $type => $value) {
                    $action['LCell'][$cell->containerId()][$cell->cellId()][$type] = $value;
                }
            }
        }
        return $action;
    }
}
