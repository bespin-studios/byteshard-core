<?php

namespace byteShard\Internal\Struct;

use byteShard\Enum\HttpResponseState;
use JsonSerializable;

class ClientCell implements JsonSerializable
{
    public array             $components;
    public HttpResponseState $state;

    public function __construct(public ClientCellProperties $cell, ClientCellComponent ...$components)
    {
        $this->components = $components;
        $this->state      = HttpResponseState::ERROR;
    }

    public function setState(HttpResponseState $state): void
    {
        $this->state = $state;
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($value) => !empty($value));
    }
}