<?php

namespace byteShard;

use byteShard\Internal\CellContent;

abstract class DynamicCellContent extends CellContent
{
    abstract public function getDynamicContentClassName(): string;

    public function getDynamicCell(string $dynamicClassName): Cell
    {
        $this->cell->setContentClassName(str_replace('App\\Cell\\', '', $dynamicClassName));
        return $this->cell;
    }
}