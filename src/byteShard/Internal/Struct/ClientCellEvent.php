<?php

namespace byteShard\Internal\Struct;

class ClientCellEvent
{
    public function __construct(public readonly string $event, public readonly string $handler)
    {

    }
}