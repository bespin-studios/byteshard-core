<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\File\Excel;

use byteShard\Internal\File\ExcelBase;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Class Column
 * @package byteShard\File\Excel
 */
class Column extends ExcelBase
{
    public function __construct(int|string|array|null $columnString = null, ?string $propertyName = null)
    {
        if ($columnString !== null) {
            if (is_numeric($columnString)) {
                $this->columnString = Coordinate::stringFromColumnIndex((int)$columnString);
                $this->columnIndex  = (int)$columnString;
            } elseif (is_string($columnString)) {
                $this->columnString = $columnString;
                $this->columnIndex  = Coordinate::columnIndexFromString($columnString) - 1;
            } elseif (is_array($columnString)) {
                $this->columnArray = $columnString;
            }
        }
        if ($propertyName !== null) {
            $this->propertyName = $propertyName;
        }
    }

    /**
     * key value array where key is the row and value is the string to look for
     * @param array $array
     */
    public function setColumnByHeader(array $array): void
    {
        foreach ($array as $row => $string) {
            $this->columnArray[$row] = $string;
        }
    }

    public function getCalculatedValue(bool $bool = true): void
    {
        $this->calculatedValue = $bool;
    }

    public function setDateColumn(bool $bool = true): void
    {
        $this->date = $bool;
    }
}
