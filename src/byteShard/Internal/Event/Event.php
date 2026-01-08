<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Event;

/**
 * Class Event
 * @package byteShard\Internal\Event
 */
abstract class Event
{
    protected static string $event            = '';
    protected static string $function         = '';
    protected static string $contentEventName = '';

    // usually the event is triggered by the same event, but for example onCheck is in fact an onChange event, the if checked/unchecked logic is implemented serverside
    protected static string $deviatingEvent = '';

    /**
     * Event constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return string
     */
    public function getEventType(): string
    {
        return static::$event;
    }

    /**
     * @return string
     * @internal
     */
    public function getContentEventName(): string
    {
        return static::$contentEventName !== '' ? static::$contentEventName : static::$event;
    }

    /**
     * @return string
     * @internal
     */
    public static function getEventNameForEventHandler(): string
    {
        return static::$contentEventName !== '' ? static::$contentEventName : static::$event;
    }

    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return static::$function !== '' ? static::$function : 'do'.ucfirst(static::$event);
    }

    public static function getDeviatingEvent(): string
    {
        return static::$deviatingEvent;
    }
}
