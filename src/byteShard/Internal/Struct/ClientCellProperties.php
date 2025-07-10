<?php

namespace byteShard\Internal\Struct;

class ClientCellProperties implements \JsonSerializable
{
    public readonly string  $nc;
    public readonly ?string $label;
    public readonly ?string $ID;
    public readonly ?string $EID;
    public readonly ?string $cellHeader;
    public readonly ?string $pollId;

    public function __construct(string $nonce, ?string $encryptedId = null, ?string $label = null, ?string $id = null, ?string $cellHeader = null, ?string $pollId = null)
    {
        $this->nc         = $nonce;
        $this->EID        = $encryptedId;
        $this->label      = $label;
        $this->ID         = $id;
        $this->cellHeader = $cellHeader;
        $this->pollId     = $pollId;
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($value) => !empty($value));
    }
}