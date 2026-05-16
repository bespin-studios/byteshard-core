<?php

namespace byteShard\Form\Event;

use byteShard\Internal\Event\FormEvent;

class OnEnter extends FormEvent
{
    protected static string $event = 'onEnter';
}