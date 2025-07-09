<?php

namespace byteShard\Internal\Struct;

class ClientCell
{
    public array $components;
    public function __construct(public ?ClientCellProperties $cell = null, ClientCellComponent ...$components)
    {
        $this->components = $components;
    }

    public function getArray(): array
    {
        return get_object_vars($this);
    }
}