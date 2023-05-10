<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Tree\TreeInterface;

/**
 * Class unsetSelectedID
 * @package byteShard\Action
 */
class UnsetSelectedID extends Action
{
    private array $cells = [];

    /**
     * UnsetSelectedID constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        parent::__construct();
        foreach ($cells as $cell) {
            $this->cells[] = Cell::getContentCellName($cell);
        }
        $this->cells = array_unique($this->cells);
        $this->addUniqueID($this->cells);
    }


    protected function runAction(): ActionResultInterface
    {
        $cells = $this->getCells($this->cells);
        foreach ($cells as $cell) {
            $cell->unsetSelectedID();
            $class = $cell->getContentClass();
            if (is_subclass_of($class, TreeInterface::class)) {
                $action['LCell'][$cell->containerId()][$cell->cellId()]['clearSelection'] = true;
            }
        }
        $action['state'] = 2;
        return new Action\ActionResultMigrationHelper($action);
    }
}
