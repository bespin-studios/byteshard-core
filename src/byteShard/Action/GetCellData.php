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
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $actionResult = new Action\CellActionResult(Action\ActionTargetEnum::Cell);
        $getData      = $this->getGetData();
        if ($getData === null) {
            // no callback executed yet, request cell data from clients
            $this->setRunNested(false);
            foreach ($this->sources as $cellName => $types) {
                foreach ($types as $type => $value) {
                    $actionResult->addCellCommand([$cellName], $type, $value);
                }
            }
        } else {
            $this->getActionInitDTO()->eventContainer->setProcessedGetCellDataResponse($getData);
            $this->getActionInitDTO()->cell->setGetDataActionClientData($getData);
            $this->setRunNested();
        }
        return $actionResult;
    }
}
