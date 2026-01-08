<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\Export\ExportType;
use byteShard\Enum\HttpResponseState;
use byteShard\Grid\GridInterface;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

/**
 * Class CellExport
 * @package byteShard\Action
 */
class CellExport extends Action\ExportAction implements Action\ExportInterface
{
    private string $cell;

    public function __construct(string $cell, ExportType $type = ExportType::XLS)
    {
        parent::__construct($type, 180);
        $this->cell = Cell::getContentCellName($cell);
    }

    protected function runAction(): ActionResultInterface
    {
        $action = new Action\CellActionResult(Action\ActionTargetEnum::Cell);
        $cells = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            if (is_subclass_of($cell->getContentClass(), GridInterface::class)) {
                $action->addCellCommand([$this->cell], 'exportGrid', 'xls');
            }
        }
        return $action;
    }
    //TODO: - check if class_name of cell to be exported instanceof Grid
    //TODO: - if cell instanceof Tree, new Cell, but not getContents, getExcel (needs to be implemented in Tree class)
}
