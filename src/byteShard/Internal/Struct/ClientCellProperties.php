<?php

namespace byteShard\Internal\Struct;

class ClientCellProperties implements \JsonSerializable
{
    public readonly ?string $cn;
    public readonly ?string $label;
    public readonly ?string $ID;
    public readonly ?string $EID;
    public readonly ?string $cellHeader;
    public readonly ?string $pollId;
    public readonly bool    $ae;

    public function __construct(?string $nonce = null, ?string $encryptedId = null, ?string $label = null, ?string $id = null, ?string $cellHeader = null, ?string $pollId = null, bool $hasAsynchronousElements = false)
    {
        $this->cn         = $nonce !== null ? base64_encode($nonce) : null;
        $this->EID        = $encryptedId;
        $this->label      = $label;
        $this->ID         = $id;
        $this->cellHeader = $cellHeader;
        $this->pollId     = $pollId;
        $this->ae         = $hasAsynchronousElements;
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($value) => !empty($value));
    }
}