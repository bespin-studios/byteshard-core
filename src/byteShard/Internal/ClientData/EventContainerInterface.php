<?php

namespace byteShard\Internal\ClientData;

use byteShard\Internal\Struct\ClientData;

interface EventContainerInterface
{
    public function setProcessedClientData(?ClientData $clientData): void;
}