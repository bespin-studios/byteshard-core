<?php

namespace byteShard\Action\Cell;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Action\CellActionResult;
use byteShard\Session;

class SetContext extends Action
{
    public function __construct(private string $cell, private readonly string $context)
    {
        $this->cell = Cell::getContentCellName($this->cell);
    }
    protected function runAction(): ActionResultInterface
    {
        $result = new CellActionResult(Action\ActionTargetEnum::Layout);
        return $result->addCellCommand([$this->cell], 'setContext', Session::encrypt($this->context));
    }
}