<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Action\ClosePopup;
use byteShard\Cell;
use byteShard\Event\OnClickInterface;
use byteShard\Event\OnConfirmInterface;
use byteShard\Event\OnDoubleClickInterface;
use byteShard\Event\OnDropInterface;
use byteShard\Event\OnEmptyClickInterface;
use byteShard\Event\OnEnterInterface;
use byteShard\Event\OnInputChangeInterface;
use byteShard\Event\OnLinkClickInterface;
use byteShard\Event\OnPollInterface;
use byteShard\Event\OnPopupCloseInterface;
use byteShard\Event\OnScrollBackwardInterface;
use byteShard\Event\OnScrollForwardInterface;
use byteShard\Event\OnSelectInterface;
use byteShard\Event\OnStateChangeInterface;
use byteShard\Event\OnTabCloseInterface;
use byteShard\Event\OnUploadInterface;
use byteShard\ID\ID;
use byteShard\Event\OnChangeInterface;
use byteShard\Event\OnCheckInterface;
use byteShard\Event\OnUncheckInterface;
use byteShard\Form\Control\Checkbox;
use byteShard\Internal\Action\ActionInitDTO;
use byteShard\Internal\ClientData\EventContainerInterface;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\GetData;
use DateTimeZone;

class ActionCollector
{
    public static function getEventActions(
        ?Cell                   $cell,
        ID                      $id,
        string                  $eventInterface,
        string                  $eventId,
        string                  $objectValue,
        string                  $confirmationId,
        ?ClientData             $clientData,
        ?GetData                $getData,
        ?DateTimeZone           $clientTimeZone,
        ?array                  $objectProperties,
        string                  $eventType,
        EventContainerInterface $eventContainer,
        mixed                   $legacyId = null): array
    {
        $actions = [];
        if ($eventInterface !== '') {
            $eventContainer->setProcessedClientData($clientData);
            $eventInterface = self::transformEventInterface($eventInterface, $eventId, $clientData, $confirmationId);
            $actions        = self::getEventResultActions($eventContainer, $eventInterface, $confirmationId !== '' ? $confirmationId : $eventId, $objectValue);
            if ($actions === null) {
                Debug::debug('Event interface undefined: '.$eventInterface);
                $actions = [];
            } elseif ($eventInterface === OnConfirmInterface::class && $confirmationId !== '') {
                $actions[] = (new ClosePopup())->setPopupId($id->getEncryptedId());
            }
        }
        return self::initializeActions($actions, $id, $cell, $eventId, $confirmationId, $clientData, $getData, $clientTimeZone, $objectProperties, $eventType, $objectValue, $eventContainer, $legacyId);
    }

    private static function getEventResultActions(object $eventTarget, string $eventInterface, string $objectId, string $objectValue): ?array
    {
        return match ($eventInterface) {
            OnChangeInterface::class         => $eventTarget instanceof OnChangeInterface ? $eventTarget->onChange()->getResultActions($objectId, $objectValue) : [],
            OnCheckInterface::class          => $eventTarget instanceof OnCheckInterface ? $eventTarget->onCheck()->getResultActions($objectId, $objectValue) : [],
            OnClickInterface::class          => $eventTarget instanceof OnClickInterface ? $eventTarget->onClick()->getResultActions($objectId, $objectValue) : [],
            OnConfirmInterface::class        => $eventTarget instanceof OnConfirmInterface ? $eventTarget->onConfirm()->getResultActions($objectId, $objectValue) : [],
            OnDoubleClickInterface::class    => $eventTarget instanceof OnDoubleClickInterface ? $eventTarget->onDoubleClick()->getResultActions($objectId, $objectValue) : [],
            OnDropInterface::class           => $eventTarget instanceof OnDropInterface ? $eventTarget->onDrop()->getResultActions($objectId, $objectValue) : [],
            OnEmptyClickInterface::class     => $eventTarget instanceof OnEmptyClickInterface ? $eventTarget->onEmptyClick()->getResultActions($objectId, $objectValue) : [],
            OnEnterInterface::class          => $eventTarget instanceof OnEnterInterface ? $eventTarget->onEnter()->getResultActions($objectId, $objectValue) : [],
            OnInputChangeInterface::class    => $eventTarget instanceof OnInputChangeInterface ? $eventTarget->onInputChange()->getResultActions($objectId, $objectValue) : [],
            OnLinkClickInterface::class      => $eventTarget instanceof OnLinkClickInterface ? $eventTarget->onLinkClick()->getResultActions($objectId, $objectValue) : [],
            OnPollInterface::class           => $eventTarget instanceof OnPollInterface ? $eventTarget->onPoll()->getResultActions($objectId, $objectValue) : [],
            OnPopupCloseInterface::class     => $eventTarget instanceof OnPopupCloseInterface ? $eventTarget->onPopupClose()->getResultActions($objectId, $objectValue) : [],
            OnScrollBackwardInterface::class => $eventTarget instanceof OnScrollBackwardInterface ? $eventTarget->onScrollBackward()->getResultActions($objectId, $objectValue) : [],
            OnScrollForwardInterface::class  => $eventTarget instanceof OnScrollForwardInterface ? $eventTarget->onScrollForward()->getResultActions($objectId, $objectValue) : [],
            OnSelectInterface::class         => $eventTarget instanceof OnSelectInterface ? $eventTarget->onSelect()->getResultActions($objectId, $objectValue) : [],
            OnStateChangeInterface::class    => $eventTarget instanceof OnStateChangeInterface ? $eventTarget->onStateChange()->getResultActions($objectId, $objectValue) : [],
            OnTabCloseInterface::class       => $eventTarget instanceof OnTabCloseInterface ? $eventTarget->onTabClose()->getResultActions($objectId, $objectValue) : [],
            OnUncheckInterface::class        => $eventTarget instanceof OnUncheckInterface ? $eventTarget->onUncheck()->getResultActions($objectId, $objectValue) : [],
            OnUploadInterface::class         => $eventTarget instanceof OnUploadInterface ? $eventTarget->onUpload()->getResultActions($objectId, $objectValue) : [],
            default                          => null
        };
    }

    private static function transformEventInterface(string $eventInterface, string $eventId, ?ClientData $clientData, string $confirmationId): string
    {
        if (!empty($confirmationId)) {
            return OnConfirmInterface::class;
        }
        if ($eventInterface === OnChangeInterface::class) {
            $row = $clientData?->getRows() ?? [];
            if (isset($row[0]->{$eventId}) && $row[0]->{$eventId}->type === Checkbox::class) {
                if ($row[0]->{$eventId}->value === true) {
                    return OnCheckInterface::class;
                }
                return OnUncheckInterface::class;
            }
        }
        return $eventInterface;
    }

    /**
     * @param Action[] $actions
     */
    private static function initializeActions(array $actions, ID $id, ?Cell $cell, string $eventId, string $confirmationId, ?ClientData $clientData, ?GetData $getData, ?DateTimeZone $clientTimeZone, ?array $objectProperties, string $eventType, string $objectValue, ?EventContainerInterface $eventContainer = null, mixed $legacyId = null): array
    {
        $actionInitDTO = new ActionInitDTO($id, $cell, $eventId, $confirmationId, $clientData, $getData, $clientTimeZone, $objectProperties, $eventType, $objectValue, $eventContainer, $legacyId);
        foreach ($actions as $action) {
            $action->initializeAction($actionInitDTO);
        }
        return $actions;
    }
}
