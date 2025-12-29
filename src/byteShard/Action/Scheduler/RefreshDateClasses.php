<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Scheduler;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\ContentClassFactory;
use byteShard\Scheduler;

class RefreshDateClasses extends Action
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
            $cellName               = Cell::getContentCellName($cell);
            $this->cells[$cellName] = $cellName;
        }
        $this->addUniqueID($this->cells);
    }

    protected function runAction(): ActionResultInterface
    {
        $result = ['state' => HttpResponseState::SUCCESS->value];
        $cells  = $this->getCells($this->cells);
        foreach ($cells as $cell) {
            $date = $cell->getSelectedId()?->getSelectedDate();
            if ($date !== null) {
                $className = $cell->getContentClass();
                if (class_exists($className) && array_key_exists(Scheduler\DateTemplate::class, class_implements($className))) {
                    $range = Scheduler::getVisibleDateRange($date, $this->getClientTimeZone());
                    // if the new visible date range differs from the one in the session, call the defineDateTemplate method and return an array with classes per date
                    $scheduler = ContentClassFactory::cellContent($className, null, $cell);
                    if ($scheduler instanceof Scheduler\DateTemplate) {
                        $definedClasses = $scheduler->defineDateTemplate($range['from'], $range['to'], $this->getClientTimeZone(), Scheduler::getDatePeriod($range['from'], $range['to']));
                        // store current visible date range in session
                        $result['LCell'][$cell->containerId()][$cell->cellId()]['classes'] = $scheduler->getClassTemplateArray(...array_values($definedClasses));
                    }
                }
            }
        }
        return new Action\ActionResultMigrationHelper($result);
    }
}