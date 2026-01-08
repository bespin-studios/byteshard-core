<?php

namespace byteShard\Internal\Action;

use byteShard\Cell;
use byteShard\ID\ID;
use byteShard\Internal\ClientData\EventContainerInterface;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\GetData;
use DateTimeZone;

class ActionInitDTO
{
    public function __construct(
        public readonly ?ID                      $id,
        public readonly ?Cell                    $cell,
        public readonly string                   $eventId,
        public readonly string                   $confirmationId,
        public readonly ?ClientData              $clientData,
        public readonly ?GetData                 $getData,
        public readonly ?DateTimeZone            $clientTimeZone,
        public readonly ?array                   $objectProperties,
        public readonly string                   $eventType = '',
        public readonly string                   $objectValue = '',
        public readonly ?EventContainerInterface $eventContainer = null,
        public readonly mixed                    $legacyId = null,
    )
    {
    }
}