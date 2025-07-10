<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Internal\CellContent;
use byteShard\Internal\Struct\ClientCell;

abstract class Container
{
    private Cell $cell;
    public function __construct(?Cell $cell = null) {
        if ($cell !== null) {
            $this->cell = $cell;
        }
    }
    
    abstract public function defineContainerContent(Cell $cell): CellContent;

    public function getCellContent(): ?ClientCell
    {
        if (isset($this->cell)) {
            $content = $this->defineContainerContent($this->cell);
            return $content->getCellContent();
        }
        return null;
    }
    
    public function getCell(): ?Cell
    {
        return $this->cell;
    }
}
