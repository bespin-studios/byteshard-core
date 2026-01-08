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
 * Class ShowHeaderCell
 * @package byteShard\Action
 */
class ShowCellHeader extends Action
{
    /**
     * part of action uid
     * @var array
     */
    private array $cells;

    /**
     * ShowHeaderCell constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells) {
        $this->cells = parent::getUniqueCellNameArray(...$cells);
    }

    protected function runAction(): ActionResultInterface
    {
        $result = new CellActionResult(Action\ActionTargetEnum::Layout);
        return $result->addCellCommand($this->cells, 'showHeader', true);
    }
}