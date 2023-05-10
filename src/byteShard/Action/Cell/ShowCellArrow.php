<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Cell;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Action\CellActionResult;

/**
 * Class ShowArrowCell
 * @package byteShard\Action
 */
class ShowCellArrow extends Action
{
    /**
     * part of action uid
     * @var array
     */
    private array $cells;

    /**
     * ShowArrowCell constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        parent::__construct();
        $this->cells = array_map(function ($cell) {
            return Cell::getContentCellName($cell);
        }, array_unique($cells));
        $this->addUniqueID($this->cells);
    }

    protected function runAction(): ActionResultInterface
    {
        $result = new CellActionResult('layout');
        return $result->addCellCommand($this->cells, 'showArrow', true);
    }
}