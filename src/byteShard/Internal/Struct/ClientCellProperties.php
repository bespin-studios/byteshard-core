<?php

namespace byteShard\Internal\Struct;

class ClientCellProperties implements \JsonSerializable
{
    public ?string $label;
    public ?string $ID;
    public ?string $EID;
    public ?string $cellHeader;

    public function __construct(?string $encryptedId = null, ?string $label = null, ?string $id = null, ?string $cellHeader = null)
    {
        $this->EID   = $encryptedId;
        $this->label = $label;
        $this->ID    = $id;
        $this->cellHeader = $cellHeader;
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($value) => !empty($value));
    }
}