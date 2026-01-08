<?php

namespace byteShard\Ribbon\Control;

use byteShard\Enum\Event;
use byteShard\Internal\Ribbon\RibbonControl;
use byteShard\Internal\Traits\Disabled;
use byteShard\Internal\Traits\Image;
use byteShard\Internal\Traits\ImageDisabled;
use byteShard\Internal\Traits\Label;
use byteShard\Internal\Traits\Value;

class Input extends RibbonControl
{
    use Image;
    use ImageDisabled;
    use Disabled;
    use Label;
    use Value;

    protected string $type = 'input';

    /**
     * Accepts only Event::OnEnter
     * @phpstan-param (Event::OnEnter) ...$events
     */
    public function addEvents(Event ...$events): self
    {
        parent::addEvents(...$events);
        return $this;
    }
}