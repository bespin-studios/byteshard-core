<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use DateTimeInterface;

/**
 * Class ReloadCell
 * @package byteShard\Action
 */
class SetSchedulerDate extends Action
{
    /**
     * part of action uid
     * @var array
     */
    private array $cells = [];

    /**
     * ReloadCell constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        parent::__construct();
        foreach ($cells as $cell) {
            $cell_name               = Cell::getContentCellName($cell);
            $this->cells[$cell_name] = $cell_name;
        }
        $this->addUniqueID($this->cells);
    }

    protected function runAction(): ActionResultInterface
    {
        $cells = $this->getCells($this->cells);
        foreach ($cells as $cell) {
            $selectedDate = $cell->getSelectedId()?->getSelectedDate();
            if ($selectedDate !== null) {
                $action['LCell'][$cell->containerId()][$cell->cellId()]['updateView'] = $selectedDate->format(DateTimeInterface::ATOM);
            }
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }
}
