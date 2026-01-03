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
     * @param string $eventId
     * @param Event $event
     * @param string $objectValue
     * @internal
     */
    public function setEventForInteractiveObject(string $eventId, Event $event, string $objectValue = ''): void
    {
        $actions = $event->getActionArray();
        foreach ($actions as $action) {
            if (!$action instanceof Action\ClientExecutionInterface || $action->getClientExecution() === false) {
                $action->setEventType($event->getEventType());
                if (array_key_exists('EventActions', $this->event) === false) {
                    $this->event['EventActions'] = [];
                }
                if ($objectValue === '') {
                    if (array_key_exists($eventId, $this->event['EventActions']) === false) {
                        $this->event['EventActions'][$eventId] = [];
                    }
                } else {
                    if (array_key_exists($eventId, $this->event['EventActions']) === false) {
                        $this->event['EventActions'][$eventId][$objectValue] = [];
                    }
                }
                if (!in_array($action, $this->event['EventActions'][$eventId])) {
                    $actionUid = $action->getUniqueID();
                    $register  = true;
                    if ($objectValue === '') {
                        foreach ($this->event['EventActions'][$eventId] as $storedAction) {
                            if (($storedAction instanceof Action) && $storedAction->getUniqueID() === $actionUid) {
                                $register = false;
                                break;
                            }
                        }
                    } elseif (isset($this->event['EventActions'][$eventId][$objectValue])) {
                        foreach ($this->event['EventActions'][$eventId][$objectValue] as $storedAction) {
                            if (($storedAction instanceof Action) && $storedAction->getUniqueID() === $actionUid) {
                                $register = false;
                                break;
                            }
                        }
                    }
                    if ($register === true) {
                        if ($objectValue === '') {
                            $this->event['EventActions'][$eventId][] = $action;
                        } else {
                            $this->event['EventActions'][$eventId][$objectValue][] = $action;
                        }
                    }
                }
            }
        }
    }

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
