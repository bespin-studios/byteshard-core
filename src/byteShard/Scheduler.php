<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Enum\ContentFormat;
use byteShard\Enum\ContentType;
use byteShard\Event\CellActions;
use byteShard\Event\EventResult;
use byteShard\Event\OnEmptyClickInterface;
use byteShard\Event\OnScrollBackwardInterface;
use byteShard\Event\OnScrollForwardInterface;
use byteShard\ID\DateIDElement;
use byteShard\ID\ID;
use byteShard\Internal\CellContent;
use byteShard\Internal\Struct\ClientCell;
use byteShard\Internal\Struct\ClientCellComponent;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ClientCellProperties;
use byteShard\Scheduler\DateClass;
use byteShard\Scheduler\DateTemplate;
use byteShard\Scheduler\Entry;
use byteShard\Scheduler\Event\OnEmptyClick;
use byteShard\Scheduler\Event\OnScrollBackward;
use byteShard\Scheduler\Event\OnScrollForward;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;

abstract class Scheduler extends CellContent implements OnEmptyClickInterface, OnScrollForwardInterface, OnScrollBackwardInterface
{
    /** @var string */
    protected string $cellContentType = 'DHTMLXScheduler';
    /** @var DateTime the visible date when the scheduler is initialized */
    private DateTime  $currentDate;
    private ?DateTime $activeDate;
    private array     $classes  = [];
    private bool      $readOnly = false;
    /** @var Entry[] */
    private array $entries             = [];
    private bool  $initWithCurrentDate = true;

    public function __construct(Cell $cell)
    {
        parent::__construct($cell);
        $this->currentDate = new DateTime();
        //TODO: get client timezone from client
        $this->setClientTimeZone(new DateTimeZone('Europe/Berlin'));
        $selectedId       = $this->cell->getSelectedId();
        $this->activeDate = $selectedId?->getSelectedDate();
        /*if (is_array($selectedId) && array_key_exists('DateTime', $selectedId)) {
            $this->activeDate = $selectedId['DateTime'];
        }*/
    }

    /**
     * @API
     */
    protected function initWithCurrentDate(bool $option): void
    {
        $this->initWithCurrentDate = $option;
    }

    private function restorePreviousSelectedId(): void
    {
        if ($this->activeDate === null) {
            //get previous id
            $selectedDate = $this->cell->getSelectedId()?->getSelectedDate();
            if ($selectedDate !== null) {
                $this->setDate($selectedDate);
                $this->setActiveDate($selectedDate);
            } elseif ($this->initWithCurrentDate === true) {
                $timeZone = $this->getClientTimeZone() ?? new DateTimeZone('UTC');
                $today    = new DateTime('midnight', $timeZone);
                $this->setActiveDate($today);
                $this->cell->setSelectedID(ID::factory(new DateIDElement($today, $timeZone)));
            }
        }
    }

    public function getCellContent(): ?ClientCell
    {
        $components = parent::getComponents();
        $this->defineCellContent();
        $this->restorePreviousSelectedId();
        $this->setDateTemplate();
        $components[] = new ClientCellComponent(
            type   : ContentType::DhtmlxScheduler,
            content: $this->getContent(),
            events : $this->getCellEvents(),
            pre    : $this->getCellParameters(),
            format : ContentFormat::JSON
        );
        return new ClientCell(
            new ClientCellProperties(cellHeader: $this->getCellHeader()),
            ...$components
        );
    }

    /**
     * @API
     */
    public function addEntry(Entry $entry): self
    {
        $this->entries[] = $entry;
        return $this;
    }

    private function getContent(): array|string
    {
        if (!empty($this->entries)) {
            $entries        = [];
            $clientTimeZone = $this->getClientTimeZone();
            if ($clientTimeZone !== null) {
                $nonce = $this->cell->getNonce();
                foreach ($this->entries as $entry) {
                    $entries[] = $entry->getEntry($clientTimeZone, $nonce);
                }
            }
            return $entries;
        } else {
            return '';
        }
    }

    private function setDateTemplate(): void
    {
        if ($this instanceof DateTemplate) {
            $this->addEvents(
                new OnScrollBackward(),
                new OnScrollForward(),
                new OnEmptyClick()
            );
            $range          = self::getVisibleDateRange($this->currentDate, $this->getClientTimeZone());
            $definedClasses = $this->defineDateTemplate($range['from'], $range['to'], $this->getClientTimeZone(), self::getDatePeriod($range['from'], $range['to']));
            $this->classes  = $this->getClassTemplateArray(...array_values($definedClasses));
        }
    }

    public function onScrollBackward(): EventResult
    {
        return new EventResult(new CellActions(new Internal\Action\Scheduler\DateTemplate($this)));
    }

    public function onScrollForward(): EventResult
    {
        return new EventResult(new CellActions(new Internal\Action\Scheduler\DateTemplate($this)));
    }

    public function onEmptyClick(): EventResult
    {
        return new EventResult(new CellActions(new Internal\Action\Scheduler\DateTemplate($this)));
    }

    private function getCellEvents(): array
    {
        $events = [];
        foreach ($this->getEvents() as $event) {
            $events[] = new ClientCellEvent($event->getEventType(), $event->getFunctionName());
            $this->cell->registerContentEvent($event);
        }
        return $events;
    }

    public static function getVisibleDateRange(DateTime $date, DateTimeZone $clientTimeZone): array
    {
        $utc       = new DateTimeZone('UTC');
        $from      = DateTime::createFromFormat('Y-m-d H:i:s', $date->setTimezone($clientTimeZone)->format('Y-m-').'01 00:00:00', $clientTimeZone);
        $shiftLeft = ($from->format('w') === '0' ? 6 : (int)$from->format('w') - 1) * -1;
        $to        = clone $from;
        $from->modify($shiftLeft.' days')->setTimeZone($utc);

        $to->modify('+1 month')->modify('-1 day');
        $shiftRight = $to->format('w') === '0' ? 0 : (7 - (int)$to->format('w'));
        $to->modify('+'.$shiftRight.' days')->setTimeZone($utc);

        return [
            'from' => $from,
            'to'   => $to
        ];
    }

    public static function getDatePeriod(DateTime $from, DateTime $to, string $interval = '1 day'): DatePeriod
    {
        return new DatePeriod($from, DateInterval::createFromDateString($interval), $to->modify('+1 day'));
    }

    public function getClassTemplateArray(DateClass ...$classes): array
    {
        $result = [];
        foreach ($classes as $class) {
            $index          = $class->getDate()->Format('Y-m-d');
            $result[$index] = array_merge($result[$index] ?? [], $class->getClasses());
        }
        foreach ($result as $index => $classArray) {
            $result[$index] = implode(' ', array_unique($classArray));
        }
        return $result;
    }

    private function getCellParameters(): array
    {
        $parameters['currentDate']               = $this->currentDate->format('Y-m-d');
        $parameters['config']['dblclick_create'] = false;
        $parameters['config']['drag_create']     = false;
        if ($this->activeDate !== null) {
            $parameters['activeDate'] = $this->activeDate->format('Y-m-d');
        }
        if (!empty($this->classes)) {
            $parameters['classes'] = $this->classes;
        }
        if ($this->readOnly === true) {
            $parameters['readOnly'] = true;
        }
        return $parameters;
    }

    public function setDate(DateTime $date): self
    {
        $this->currentDate = $date;
        return $this;
    }

    public function setActiveDate(DateTime $date): self
    {
        $this->activeDate = $date;
        return $this;
    }

    /**
     * @API
     */
    public function setReadOnly(bool $readOnly = true): self
    {
        $this->readOnly = $readOnly;
        return $this;
    }
}
