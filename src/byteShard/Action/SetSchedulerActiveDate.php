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
use DateTime;

/**
 * Class SetSchedulerActiveDate
 * @package byteShard\Action
 */
class SetSchedulerActiveDate extends Action
{
    /**
     * part of action uid
     * @var array
     */
    private array     $cells;
    private ?DateTime $activeDate = null;

    /**
     * SetSchedulerActiveDate constructor.
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

    /**
     * if no activeDate is set, the selected date is used instead
     */
    public function setActiveDate(?DateTime $date): self
    {
        $this->activeDate = $date;
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $cells = $this->getCells($this->cells);
        if ($this->activeDate !== null) {
            foreach ($cells as $cell) {
                $action['LCell'][$cell->containerId()][$cell->cellId()]['activeDate'] = $this->activeDate->format('Y-m-d');
            }
        } else {
            foreach ($cells as $cell) {
                $selectedDate = $cell->getSelectedId()?->getSelectedDate();
                if ($selectedDate !== null) {
                    $activeDate = clone $selectedDate;
                    $activeDate->setTimezone($this->getClientTimeZone());
                    $action['LCell'][$cell->containerId()][$cell->cellId()]['activeDate'] = $activeDate->format('Y-m-d');
                }
            }
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }
}
