<?php

namespace byteShard\Ribbon\Control;

use byteShard\Enum\Event;
use byteShard\Internal\Ribbon\RibbonControl;
use byteShard\Internal\Traits\Big;
use byteShard\Internal\Traits\Image;
use byteShard\Internal\Traits\ImageDisabled;
use byteShard\Internal\Traits\Label;
use byteShard\Internal\Traits\Disabled;

class Button extends RibbonControl
{
    use Big;
    use Disabled;
    use Image;
    use ImageDisabled;
    use Label;

    protected string $type = 'button';

    /**
     * Accepts only Event::OnClick
     * @phpstan-param (Event::OnClick) ...$events
     */
    public function addEvents(Event ...$events): self
    {
        parent::addEvents(...$events);
        return $this;
    }
}