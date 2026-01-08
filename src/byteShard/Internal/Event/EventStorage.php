<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Event;

use byteShard\Internal\Action;

trait EventStorage
{

    /**
     * returns an empty array or an array of Action objects
     * @param string $eventId
     * @return Action[]
     * @internal
     */
    public function getActionsForEvent(string $eventId): array
    {
        if (array_key_exists('EventActions', $this->event) && array_key_exists($eventId, $this->event['EventActions'])) {
            trigger_error('Session Actions are deprecated', E_USER_DEPRECATED);
            return $this->event['EventActions'][$eventId];
        }
        return [];
    }
}
