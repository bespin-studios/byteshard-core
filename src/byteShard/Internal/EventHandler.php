<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\Enum\AccessType;
use byteShard\Environment;
use byteShard\Event\OnChangeInterface;
use byteShard\Event\OnClickInterface;
use byteShard\Event\OnDoubleClickInterface;
use byteShard\Event\OnEmptyClickInterface;
use byteShard\Event\OnEnterInterface;
use byteShard\Event\OnLinkClickInterface;
use byteShard\Event\OnPollInterface;
use byteShard\Event\OnPopupCloseInterface;
use byteShard\Event\OnScrollBackwardInterface;
use byteShard\Event\OnScrollForwardInterface;
use byteShard\Event\OnSelectInterface;
use byteShard\Event\OnStateChangeInterface;
use byteShard\Exception;
use byteShard\Grid;
use byteShard\ID;
use byteShard\Internal\ClientData\DataHarmonizer;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\Request\ElementType;
use byteShard\Internal\Request\EventType;
use byteShard\Internal\Struct;
use byteShard\Locale;
use byteShard\Popup;
use byteShard\Popup\Confirmation;
use byteShard\Popup\Message;
use byteShard\Scheduler\Event\OnScrollForward;
use byteShard\Session;
use Closure;
use DateTime;
use DateTimeZone;

/**
 * Class EventHandler
 * @package byteShard\Internal
 */
class EventHandler
{
    private Environment $environment;

    private ?Cell               $cell           = null;
    private ?string             $className      = null;
    private ?DateTimeZone       $clientTimeZone = null;
    private ?DateTime           $clientRequestDataTime;
    private CellContent         $cellContent;
    private string              $nonce;
    private ?ID\ID              $id;
    private array               $objectProperties;
    private Request             $request;
    private ?ContainerInterface $container      = null;

    /**
     * @param Environment $environment
     * @param Request $request
     */
    public function __construct(Environment $environment, Request $request)
    {
        $this->id               = $request->getId();
        $this->nonce            = $request->getCellNonce();
        $this->objectProperties = $request->getObjectProperties();
        $timeZone               = $request->getClientTimeZone();
        if ($timeZone instanceof DateTimeZone) {
            $this->clientTimeZone = $timeZone;
            Session::setClientTimeZone($timeZone);
        }
        $this->clientRequestDataTime = $request->getDataAge();
        $this->request               = $request;
        $this->environment           = $environment;

        if ($this->id->isCellId() === true) {
            $this->cell = Session::getCell($this->id);
            if ($this->cell !== null) {
                $this->className = $this->cell->getContentClass();
                if ($this->nonce === '') {
                    $this->nonce = $this->cell->getNonce();
                }
            }
        }
    }

    public function getEventResult(): array
    {
        $eventType  = $this->request->getEvent();
        $affectedId = $this->request->getAffectedId();
        $data       = $this->request->getData();

        [$eventId, $objectValue, $clientData, $getData, $errorMessages] = DataHarmonizer::getHarmonizedData($this->request, $this->nonce, $this->objectProperties, $this->clientTimeZone, $this->cell, $this->clientRequestDataTime, $this->getCellContent(...));

        if (!empty($errorMessages)) {
            return (new Message(implode('<br>', $errorMessages)))->getNavigationArray();
        }

        //TODO: onSelect is shared on Tab and Tree
        if (in_array($this->request->getElementType(), [ElementType::DhxForm, ElementType::DhxGrid, ElementType::DhxTree, ElementType::DhxToolbar, ElementType::BsPoll])) {
            [$eventType, $eventId, $objectValue, $confirmationId, $clientData, $getData] = $this->restoreConfirmationData($eventType, $eventId, $objectValue, $clientData, $getData);
            $this->cell?->setActionId($eventId);
            switch ($eventType) {
                case EventType::OnClick:
                case EventType::OnButtonClick:
                    return $this->defaultEvent($eventId, $objectValue, $confirmationId, $clientData, $getData, OnClickInterface::class, $this->request->getData());
                case EventType::OnEnter:
                    return $this->defaultEvent($eventId, $objectValue, $confirmationId, $clientData, $getData, OnEnterInterface::class, $this->request->getData());
                case EventType::OnStateChange:
                    return $this->defaultEvent($eventId, $objectValue, $confirmationId, $clientData, $getData, OnStateChangeInterface::class, $this->request->getData());
                case EventType::OnChange:
                    return $this->defaultEvent($eventId, $objectValue, $confirmationId, $clientData, $getData, OnChangeInterface::class, $this->request->getData());
                case EventType::OnInputChange:
                    return $this->defaultEvent($eventId, $objectValue, $confirmationId, $clientData, $getData, OnChangeInterface::class, $this->request->getData(), $this->onFormInputChangeCallback(...));
                case EventType::OnCellEdit:
                    return $this->onGridCellEdit($eventId, $objectValue, $confirmationId, $clientData, $getData, '', $this->request->getData());
                case EventType::OnGridLink:
                    return $this->defaultEvent($eventId, $objectValue, $confirmationId, $clientData, $getData, OnLinkClickInterface::class, $this->request->getData());
                case EventType::OnRowSelect:
                case EventType::OnSelect:
                    return $this->defaultEvent('onSelect', $objectValue, $confirmationId, $clientData, $getData, OnSelectInterface::class, json_decode(Session::decrypt($affectedId), true));
                case EventType::OnDblClick:
                    return $this->defaultEvent('onDoubleClick', $objectValue, $confirmationId, $clientData, $getData, OnDoubleClickInterface::class, json_decode(Session::decrypt($affectedId), true));
                case EventType::OnPoll:
                    return !str_starts_with($eventId, 'pollOn:') ? ['state' => 2] : $this->defaultEvent($eventId, $objectValue, $confirmationId, $clientData, $getData, OnPollInterface::class, $this->request->getData());
            }
        }

        // TODO: harmonize all other event types as well.
        // create actions for stuff like onTabChange, onCollapse and so on
        // create interfaces so these events could possibly be overridden on the app level
        return match ($eventType) {
            EventType::OnPanelResizeFinish                          => $this->onPanelResizeFinish($affectedId, $data),
            EventType::OnCollapse                                   => $this->onCollapse($affectedId),
            EventType::OnExpand                                     => $this->onExpand($affectedId),
            EventType::OnSelect                                     => $this->onTabChange($affectedId),
            EventType::OnTabClose                                   => $this->onTabClose($affectedId),
            EventType::OnJSLinkClicked                              => $this->onJsLinkClick($affectedId, $data),
            EventType::OnDrop                                       => $this->onGridRowDrop($affectedId, $data),
            EventType::OnInfo                                       => $this->onInfo($affectedId),
            EventType::OnEmptyClick                                 => $this->onEmptyClick($affectedId, $data),
            EventType::OnScrollForward, EventType::OnScrollBackward => $this->doOnViewChance(OnScrollForward::getEventNameForEventHandler(), $affectedId, $data),
            EventType::OnPopupClose                                 => $this->onPopupClose($affectedId),
            default                                                 => [],
        };
    }

    private function restoreConfirmationData(EventType $eventType, string $eventId, string $objectValue, ?object $clientData, ?object $getData): array
    {
        $confirmationId = '';
        if ($eventId !== Confirmation::BUTTON_ID) {
            return [$eventType, $eventId, $objectValue, $confirmationId, $clientData, $getData];
        }
        $eventId        = Session::decrypt($clientData->{Confirmation::ACTION_FIELD});
        $eventType      = EventType::from(Session::decrypt($clientData->{Confirmation::EVENT_TYPE}));
        $objectValue    = Session::decrypt($clientData->{Confirmation::OBJECT_VALUE});
        $confirmationId = Session::decrypt($clientData->{Confirmation::CONFIRMATION_ID_FIELD});
        if (isset($clientData->{Confirmation::GET_DATA_FIELD})) {
            if (extension_loaded('zlib') === true) {
                $getData = unserialize(gzuncompress(Session::decrypt($clientData->{Confirmation::GET_DATA_FIELD})));
            } else {
                $getData = unserialize(Session::decrypt($clientData->{Confirmation::GET_DATA_FIELD}));
            }
        }
        if (extension_loaded('zlib') === true) {
            $clientData = unserialize(gzuncompress(Session::decrypt($clientData->{Confirmation::CLIENT_DATA_FIELD})));
        } else {
            $clientData = unserialize(Session::decrypt($clientData->{Confirmation::CLIENT_DATA_FIELD}));
        }
        return [$eventType, $eventId, $objectValue, $confirmationId, $clientData, $getData ?? null];
    }

    private function getActions(string $eventId, string $objectValue, string $confirmationId, string $eventInterface = '', ?Struct\ClientData $clientData = null, ?Struct\GetData $getData = null): array
    {
        if ($this->cell !== null) {
            return ActionCollector::getEventActions($this->cell, $this->id, $eventInterface, $eventId, $objectValue, $confirmationId, $clientData, $getData, $this->clientTimeZone, $this->request->getObjectProperties(), $this->request->getEvent()->value, function () {
                return $this->getCellContent();
            });
        }
        return [];
    }

    private function getCellContent(): CellContent
    {
        if (!isset($this->cellContent)) {
            $this->cellContent = new $this->className($this->cell);
        }
        if ($this->clientTimeZone !== null) {
            $this->cellContent->setClientTimeZone($this->clientTimeZone);
        }
        return $this->cellContent;
    }

    private function runActions(array $data, Action ...$actions): array
    {
        $result['state'] = 2;
        $mergeArray      = [];
        foreach ($actions as $action) {
            $mergeArray[] = $action->getResult($this->cell ?? $this->container, $data);
        }
        $result          = array_merge_recursive($result, ...$mergeArray);
        $result['state'] = $this->getState($result['state']);
        return $result;
    }

    private function getState(array|int $state): int
    {
        return is_array($state) ? min(2, min($state)) : $state;
    }

    private function setCellHeight(string $tabName, string $cellName, int $height): void
    {
        $this->environment->storeUserSetting($tabName, $cellName, Cell::HEIGHT, 'Cell', $height);
    }

    private function setCellWidth(string $tabName, string $cellName, int $width): void
    {
        $this->environment->storeUserSetting($tabName, $cellName, Cell::WIDTH, 'Cell', $width);
    }

    /************************
     * * EVENT PROCESSING * *
     ************************/

    private function defaultEvent(string $eventId, string $objectValue, string $confirmationId, ?Struct\ClientData $clientData, ?Struct\GetData $getData, string $interface, array $data, ?Closure $callback = null): array
    {
        $actions = $this->getActions($eventId, $objectValue, $confirmationId, $interface, $clientData, $getData);
        $result  = $this->runActions($data, ...$actions);
        if ($callback !== null) {
            return $callback($result);
        }
        return $result;
    }

    private function onGridCellEdit(string $eventId, string $objectValue, string $confirmationId, ?Struct\ClientData $clientData, ?Struct\GetData $getData, string $interface, array $data, ?Closure $callback = null)
    {
        $update = $this->getCellContent();
        $traits = class_uses(CellContent::class);
        if ($traits !== false && in_array(PermissionImplementation::class, $traits)) {
            if ($update->getAccessType() < AccessType::RW) {
                return (new Message(Locale::get('byteShard.grid.update.noPermission')))->getNavigationArray();
            }
        }
        if ($update instanceof Grid\GridInterface) {
            return $update->newRunClientGridUpdate($clientData);
        }
        return (new Message(Locale::get('byteShard.eventHandler.gridEdit.class.wrongParent')))->getNavigationArray();
    }

    /**
     * @param string $affectedCells
     * @param array $resizeData
     * @return array
     */
    private function onPanelResizeFinish(string $affectedCells, array $resizeData): array
    {
        $tab = Session::getTab($this->id);
        if ($tab !== null) {
            $layout    = $tab->getLayout();
            $direction = $layout->getResizeDirection($affectedCells);
            if (array_key_exists('type', $resizeData)) {
                unset($resizeData['type']);
            }
            if (isset($resizeData['autoSizes'], $resizeData['cells'])) {
                $autoSizes = $resizeData['autoSizes'];
                $cellSizes = $resizeData['cells'];
                if (is_array($autoSizes) && is_array($cellSizes)) {
                    if ($direction === 'w' && array_key_exists(0, $autoSizes)) {
                        $cells = explode(';', $autoSizes[0]);
                        foreach ($cells as $cell) {
                            if (array_key_exists($cell, $cellSizes)) {
                                unset($cellSizes[$cell]);
                            }
                        }
                    } elseif ($direction === 'h' && array_key_exists(1, $autoSizes)) {
                        $cells = explode(';', $autoSizes[1]);
                        foreach ($cells as $cell) {
                            if (array_key_exists($cell, $cellSizes)) {
                                unset($cellSizes[$cell]);
                            }
                        }
                    }

                    $tabName = $tab->getNewId()->getTabId();
                    foreach ($cellSizes as $cellName => $cellSize) {
                        $cellId = clone $tab->getNewId();
                        $cellId->addIdElement(new ID\CellIDElement($tabName.'\\'.$cellName));
                        $cell = $tab->getCell($cellId);
                        if ($direction === 'w' && array_key_exists('width', $cellSize)) {
                            if ($cell instanceof Cell) {
                                $cell->setWidthOnResize((int)$cellSize['width']);
                            }
                            $this->setCellWidth($tabName, $cellName, round($cellSize['width']));
                        } elseif ($direction === 'h' && array_key_exists('height', $cellSize)) {
                            if ($cell instanceof Cell) {
                                $cell->setHeightOnResize((int)$cellSize['height']);
                            }
                            $this->setCellHeight($tabName, $cellName, round($cellSize['height']));
                        }
                    }
                }
            }
        }
        return ['state' => 2];
    }

    /**
     * @param string $cellName
     * @return array
     */
    private function onCollapse(string $cellName): array
    {
        $tab = Session::getTab($this->id);
        if ($tab !== null) {
            $tabName = $tab->getNewId()->getTabId();
            $cellId  = clone $tab->getNewId();
            $cellId->addIdElement(new ID\CellIDElement($tabName.'\\'.$cellName));
            $cell = $tab->getCell($cellId);
            $cell->setCollapsed();
            $this->environment->storeUserSetting($tabName, $cellName, Cell::COLLAPSED, 'Cell', 1);
        }
        return ['state' => 2];
    }

    /**
     * @param string $cellName
     * @return array
     */
    private function onExpand(string $cellName): array
    {
        $tab = Session::getTab($this->id);
        if ($tab !== null) {
            $tabName = $tab->getNewId()->getTabId();
            $cellId  = clone $tab->getNewId();
            $cellId->addIdElement(new ID\CellIDElement($tabName.'\\'.$cellName));
            $cell = $tab->getCell($cellId);
            $cell->setCollapsed(false);
            $this->environment->deleteUserSetting($tabName, $cellName, Cell::COLLAPSED, 'Cell');
        }
        return ['state' => 2];
    }

    public function onTabChange(string $affectedId = ''): array
    {
        $tabId = null;
        if ($affectedId !== '') {
            $tabId = ID\ID::decryptFinalImplementation($affectedId);
        }
        if ($tabId === null) {
            $tabId = $this->id;
        }
        if ($tabId !== null && $tabId->isTabId() === true) {
            // if only a popup is opened we don't need to process a tab change
            if ($tabId->isPopupId() === false) {
                Session::setSelectedTab($tabId);
                $this->environment->setLastTab($tabId);
            }
        }
        return ['state' => 2];
    }

    /**
     * @param string $tabId
     * @return array
     * @throws Exception
     */
    public function onTabClose(string $tabId): array
    {
        $result['state'] = 2;
        /*if (($tab = Session::getTab($tabId)) !== null && ($actions = $tab->getContentActions('onTabClose')) !== null) {
            $merge_array = array();
            foreach ($actions as $action) {
                if ($action instanceof Action) {
                    $merge_array[] = $action->getResult($tab, $tabId);
                }
            }
            $result = array_merge_recursive($result, ...$merge_array);
        }*/
        $result['state'] = $this->getState($result['state']);
        return $result;
    }

    /**
     * @param string $objectId
     * @param array $data
     * @return array
     */
    public function onJsLinkClick(string $objectId, array $data): array
    {
        return ['state' => 2];
        // onJsLinkClick is not in use, currently only onGridLink is used. Check if that implementation also works in popups, if not, fix it, remove this implementation afterwards.
        /*$result['state'] = 2;
        if (array_key_exists('colID', $data) && ($cell = Session::getCell($this->id)) !== null) {
            $actions    = $cell->getActionsForEvent($data['colID']);
            $mergeArray = [];
            $cell->setClickedLinkID($objectId);
            foreach ($actions as $action) {
                if ($action->getEventType() === 'onLinkClick') {
                    $mergeArray[] = $action->getResult($cell, $data);
                }
            }
            $result = array_merge_recursive($result, ...$mergeArray);
        }
        $result['state'] = $this->getState($result['state']);
        return $result;*/
    }

    public function onGridRowDrop(string $rowId, array $data): array
    {
        //TODO: use ID\ID to decrypt instead of ID
        //TODO: think of a way how to inject event data into action, then use $this->getActions to call onDrop method
        $foo = ID\ID::decryptFinalImplementation($rowId);

        return [];
        /*$cellContent = $this->getCellContent();
        $cellContent->setDragged((object)['draggedRow' => $draggedRow, 'droppedBelowRow' => $droppedBelow]);
        $actions = $this->getActions('', '', '', OnDropInterface::class, null, null);
        return $this->runActions(['draggedRow' => $draggedRow, 'droppedBelowRow' => $droppedBelow], ...$actions);*/
    }

    /**
     * @param string $objectId
     * @return array
     */
    public function onInfo(string $objectId): array
    {
        $result['LCell'][$this->id->getEncryptedContainerId()][$this->id->getPatternCellId()]['showInfo'][$objectId] = 'Foo bar baz';
        $result['state']                                                                                             = 2;
        return $result;
    }

    public function onEmptyClick(string $objectId, array $data): array
    {
        $result['state'] = 0;
        try {
            $selectedDate = new DateTime($objectId);
            $selectedDate->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception) {
            return $result;
        }
        $data    = ['!#SelectedSchedulerDate' => $selectedDate];
        $actions = $this->getActions('', '', '', OnEmptyClickInterface::class);
        return $this->runActions($data, ...$actions);
    }

    public function doOnViewChance(string $event, string $date, array $data): array
    {
        $result['state'] = 0;
        try {
            $selectedDate = new DateTime($date);
            $selectedDate->setTimezone(new DateTimeZone('UCT'));
        } catch (\Exception) {
            return $result;
        }
        $data    = array_merge($data, [
            'DateTime' => $selectedDate,
            'TimeZone' => $this->clientTimeZone
        ]);
        $actions = [];
        if ($event === 'onScrollForward') {
            $actions = $this->getActions('', '', '', OnScrollForwardInterface::class);
        } elseif ($event === 'onScrollBackward') {
            $actions = $this->getActions('', '', '', OnScrollBackwardInterface::class);
        }
        return $this->runActions($data, ...$actions);
    }

    /**
     * @param string $data
     * @return array
     */
    public function onPopupClose(string $data): array
    {
        $actions = [];
        try {
            $decryptedId = Session::decrypt($data);
            $decodedId   = json_decode($decryptedId, true);
        } catch (\Exception) {
            $decodedId = [];
        }
        if (array_key_exists(ID\ID::POPUPID, $decodedId)) {
            $className = str_starts_with(strtolower($decodedId[ID\ID::POPUPID]), 'app\\popup') ? $decodedId[ID\ID::POPUPID] : 'App\\Popup\\'.$decodedId[ID\ID::POPUPID];
            if (class_exists($className)) {
                $popup = new $className();
                if ($popup instanceof Popup) {
                    if (array_key_exists(ID\ID::TABID, $decodedId)) {
                        $popup->addTabIdElement(new ID\TabIDElement($decodedId[ID\ID::TABID]));
                    }
                    $this->container = $popup;
                }
                $actions = ActionCollector::getEventActions(null, $this->id, OnPopupCloseInterface::class, '', '', '', null, null, $this->clientTimeZone, $this->request->getObjectProperties(), $this->request->getEvent()->value, null, $className);
            }
        }
        return $this->runActions([], ...$actions);
    }

    /******************************
     * * POST ACTION PROCESSING * *
     ******************************/

    private function onFormInputChangeCallback(array $response): array
    {
        $response['LCell'][$this->id->getEncryptedContainerId()][$this->id->getPatternCellId()]['hideInputLoader'][$this->request->getAffectedId()] = true;
        return $response;
    }

}
