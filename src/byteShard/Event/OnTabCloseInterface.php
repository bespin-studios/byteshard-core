<?php

namespace byteShard\Event;

interface OnTabCloseInterface
{
    public function onTabClose(): EventResult;
}