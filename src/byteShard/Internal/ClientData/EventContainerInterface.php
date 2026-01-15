<?php

namespace byteShard\Internal\ClientData;

use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\GetData;

interface EventContainerInterface
{
    public function setProcessedClientData(?ClientData $clientData): void;
    public function setProcessedGetCellDataResponse(?GetData $getData): void;
}