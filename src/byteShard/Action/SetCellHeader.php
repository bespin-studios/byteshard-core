<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Action\CellActionResult;

/**
 * Class SetCellHeader
 * @package byteShard\Action
 */
class SetCellHeader extends Action
{
    private string $cell;
    private string $label;

    /**
     * SetCellHeader constructor.
     */
    public function __construct(string $cell, ?string $label = null)
    {
        parent::__construct();
        $this->cell = Cell::getContentCellName($cell);
        if ($label !== null) {
            $this->label = $label;
        }
    }

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $label  = mb_convert_encoding($this->label ?? '', 'UTF-8', 'auto');
        $result = new CellActionResult('layout');
        return $result->addCellCommand([$this->cell], 'setCellHeaderText', $label);
    }
}
