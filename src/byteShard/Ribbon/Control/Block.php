<?php

namespace byteShard\Ribbon\Control;

use byteShard\Internal\Ribbon\RibbonControl;
use byteShard\Internal\Ribbon\RibbonObjectInterface;
use byteShard\Internal\Traits\Label;

class Block extends RibbonControl
{
    use Label;

    protected string $type = 'block';

    public function addRibbonObject(RibbonObjectInterface ...$ribbonObjects): RibbonObjectInterface
    {
        foreach ($ribbonObjects as $ribbonObject) {
            $this->nested[] = $ribbonObject;
        }
        return $this;
    }
}