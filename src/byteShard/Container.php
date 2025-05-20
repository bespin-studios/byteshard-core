<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Internal\CellContent;

abstract class Container
{
    private Cell $cell;
    public function __construct(?Cell $cell = null) {
        if ($cell !== null) {
            $this->cell = $cell;
        }
    }
    
    abstract public function defineContainerContent(Cell $cell): CellContent;

    public function getCellContent(): array
    {
        if (isset($this->cell)) {
            $content = $this->defineContainerContent($this->cell);
            return $content->getCellContent();
        }
        return [];
    }
    
    public function getCell(): ?Cell
    {
        return $this->cell;
    }
}
