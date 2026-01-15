<?php

namespace byteShard\Event;

interface OnCellEditInterface
{
    public function onCellEdit(): EventResult;
}
