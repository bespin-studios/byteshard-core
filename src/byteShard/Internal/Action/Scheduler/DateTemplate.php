<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Action\Scheduler;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\ContentClassFactory;
use byteShard\Scheduler;

class DateTemplate extends Action
{
    private string $cell;

    public function __construct(string $cell)
    {
        $this->cell = Cell::getContentCellName($cell);
    }

    protected function runAction(): ActionResultInterface
    {
        $result = ['state' => HttpResponseState::SUCCESS->value];
        $cells  = $this->getCells([$this->cell]);
        $id     = $this->getLegacyId();

        $date = null;
        if (is_array($id)) {
            if (array_key_exists('DateTime', $id)) {
                $date = $id['DateTime'];
            } elseif (array_key_exists('!#SelectedSchedulerDate', $id)) {
                $date = $id['!#SelectedSchedulerDate'];
            }
        }
        if ($date !== null) {
            foreach ($cells as $cell) {
                $className = $cell->getContentClass();
                if (class_exists($className) && array_key_exists(Scheduler\DateTemplate::class, class_implements($className)) && $date !== null) {
                    $clientTimeZone = $this->getClientTimeZone();
                    $range          = Scheduler::getVisibleDateRange($date, $clientTimeZone);
                    $rangeString    = $range['from']->format('YmdHis').$range['to']->format('YmdHis');
                    // if the new visible date range differs from the one in the session, call the defineDateTemplate method and return an array with classes per date
                    if ($cell->getVisibleDateRange() !== $rangeString) {
                        $scheduler = ContentClassFactory::cellContent($className, null, $cell);
                        if ($scheduler instanceof Scheduler && $scheduler instanceof Scheduler\DateTemplate) {
                            $definedClasses = $scheduler->defineDateTemplate($range['from'], $range['to'], $clientTimeZone, Scheduler::getDatePeriod($range['from'], $range['to']));
                            $classes        = $scheduler->getClassTemplateArray(...array_values($definedClasses));
                            // store current visible date range in session
                            $cell->setVisibleDateRange($rangeString);
                            $result['LCell'][$cell->containerId()][$cell->cellId()]['classes'] = $classes;
                        }
                    }
                }
            }
        }
        return new Action\ActionResultMigrationHelper($result);
    }
}