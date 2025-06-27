<?php

namespace byteShard\Internal\Permission;

use byteShard\Cell;
use byteShard\Layout\Enum\Pattern;
use byteShard\TabNew;

class NoApplicationPermissionError extends TabNew
{
    public function defineTabContent(): void
    {
        $this->setPattern(Pattern::PATTERN_1C);
        //TODO: the byteShard\Internal\Permission\Cell\NoPermission\NoPermissionCell will be loaded.
        //Make this more explicit
        $this->addCell(new Cell());
    }
}