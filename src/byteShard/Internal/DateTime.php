<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Database;
use byteShard\Internal\Database\MySQL;
use byteShard\Internal\Database\PGSQL\PDO\Connection;
use DateTimeZone;

/**
 * Class DateTime
 * @package byteShard\Internal
 */
class DateTime extends \DateTime
{
    public function __construct(string $time = 'now', ?DateTimeZone $timezone = null)
    {
        if (is_numeric($time)) {
            if (strlen($time) === 14) {
                parent::__construct(substr($time, 0, 4).'-'.substr($time, 4, 2).'-'.substr($time, 6, 2).' '.substr($time, 8, 2).':'.substr($time, 10, 2).':'.substr($time, 12, 2), $timezone);
            } elseif (strlen($time) === 8) {
                parent::__construct(substr($time, 0, 4).'-'.substr($time, 4, 2).'-'.substr($time, 6, 2).' 12:00:00', $timezone);
            } else {
                parent::__construct($time, $timezone);
            }
        } else {
            parent::__construct($time, $timezone);
        }
    }

    public function getLocalTime(): string
    {
        return $this->format('d.m.Y H:i');
    }

    public function getLocalDateTime(): string
    {
        return $this->format('d.m.Y - H:i:s');
    }

    public function getDateForGantt(): void
    {

    }

    public function getDateTimeForGantt(): string
    {
        return $this->format('d-m-Y H:i');
    }

    public function getDateTimeForDB(): string
    {
        $connection = Database::getConnection();
        if ($connection instanceof Connection) {
            return $this->format('Y-m-d H:i:s');
        } elseif ($connection instanceof MySQL\Connection) {
            return $this->format('Y-m-d H:i:s');
        }
        return $this->format('Y-m-d H:i:s');
    }

    public function getBigintDateTime(): float
    {
        return (float)$this->format('YmdHis');
    }

    public function getDurationInDecimalHours(DateTime|\DateTime|string $startDate, DateTime|\DateTime|string|null $endDate = null): float
    {
        if (!$startDate instanceof \DateTime) {
            $startDate = new DateTime($startDate);
        }
        if ($endDate !== null) {
            if (!$endDate instanceof \DateTime) {
                $endDate = new DateTime($endDate);
            }
            $diff = $endDate->diff($startDate);
        } else {
            $diff = $this->diff($startDate);
        }
        return (((float)($diff->format('%d')) * 24) + (float)($diff->format('%h')) + ((float)($diff->format('%i')) / 60)) * (($diff->invert === 1) ? -1 : 1);
    }
}
