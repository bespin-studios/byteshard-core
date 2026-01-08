<?php

namespace byteShard\Event;

interface OnConfirmInterface
{
    public function onConfirm(): EventResult;
}