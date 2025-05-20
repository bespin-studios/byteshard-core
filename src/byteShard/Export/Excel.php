<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Export;

/**
 * Class Excel
 * @package byteShard\Export
 */
abstract class Excel
{
    protected array $format = [];
    protected       $content;

    public function setColWidth(float $width, ?int $col = null): void
    {
        if ($col === null) {
            $this->format['colWidth'][] = $width;
        } else {
            $this->format['colWidth'][$col] = $width;
        }
    }

    public function setRowHeight(float $height, ?int $row = null): void
    {
        if ($row === null) {
            $this->format['rowHeight'][] = $height;
        } else {
            $this->format['rowHeight'][$row] = $height;
        }
    }

    public function setRowGroupLevel(int $row, int $level): void
    {
        $this->format['grouping']['row'][$row] = $level;
    }

    public function setColGroupLevel(int $col, int $level): void
    {
        $this->format['grouping']['col'][$col] = $level;
    }

    public function setFreezePane(int $row, int $col): void
    {
        $this->format['freezePane'] = array('row' => $row, 'col' => $col);
    }

    public function getFormat(): array
    {
        return $this->format;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }
}
