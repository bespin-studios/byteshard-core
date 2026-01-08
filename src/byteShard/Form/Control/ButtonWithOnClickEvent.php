<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Form\Control;
use byteShard\Form\Event;
use byteShard\Internal\Action;
use byteShard\Internal\Form\ButtonInterface;

/**
 * Class ButtonWithOnClickEvent
 * @package byteShard\Form\Control
 */
class ButtonWithOnClickEvent extends Button implements ButtonInterface
{
    public function __construct($id) {
        parent::__construct($id);
        $this->addEvents(new Event\OnButtonClick());
    }
}
