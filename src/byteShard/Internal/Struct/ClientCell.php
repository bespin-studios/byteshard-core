<?php

namespace byteShard\Internal\Struct;

use byteShard\Enum\ContentType;
use byteShard\Enum\HttpResponseState;
use JsonSerializable;

class ClientCell implements JsonSerializable
{
    public array                $content;
    public HttpResponseState    $state;
    public readonly ContentType $type;

    public function __construct(public ClientCellProperties $setup, ClientCellComponent|ContentComponent ...$components)
    {
        $this->content = $components;
        $this->state   = HttpResponseState::ERROR;
        $this->type    = ContentType::DhtmlxLayoutCell;
    }

    public function setState(HttpResponseState $state): void
    {
        $this->state = $state;
    }

    public function jsonSerialize(): array
    {
        return [
            'content' => array_filter(get_object_vars($this), fn($value) => !empty($value)),
            'state'   => $this->state,
        ];
    }
}