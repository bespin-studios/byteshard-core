<?php

namespace byteShard\Internal\Struct;

class ClientCellEvent
{
    public function __construct(public readonly string $event, public readonly string $handler)
    {

    }

    public static function getUniqueEvents(ClientCellEvent ...$events): array
    {
        $unique = [];
        foreach ($events as $event) {
            $unique[$event->event.'|'.$event->handler] = $event;
        }
        return array_values($unique);
    }
}