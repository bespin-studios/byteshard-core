<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Action\ConfirmAction;
use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action\ActionInitDTO;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Action\ControlIdInterface;
use byteShard\Internal\Action\ExportInterface;
use byteShard\Internal\ClientData\EventContainerInterface;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\GetData;
use byteShard\Session;
use byteShard\ID;
use Closure;
use DateTimeZone;
use UnitEnum;

/**
 * Class Action
 * @package byteShard\Internal
 */
abstract class Action
{
    protected string                 $localeBaseToken  = '';
    private array                    $conditionArgs    = [];
    private array                    $nested           = [];
    private array                    $permissions      = [];
    private array                    $staticCallback;
    private bool                     $runNested        = true;
    private mixed                    $conditionCallback;
    private string                   $eventType        = '';
    private ?ClientData              $clientData       = null;
    private ?GetData                 $getData          = null;
    private ?DateTimeZone            $clientTimeZone;
    private array                    $objectProperties = [];
    private ?EventContainerInterface $eventContainer   = null;
    private ?ActionInitDTO           $actionInitDTO    = null;

    protected static function getUniqueCellNameArray(...$cells): array
    {
        return array_map(function ($cell) {
            return Cell::getContentCellName($cell);
        }, array_unique($cells));
    }

    public function initializeAction(ActionInitDTO $actionInitDTO): self
    {
        $this->actionInitDTO = $actionInitDTO;
        $this->setClientTimeZone($actionInitDTO->clientTimeZone);
        $this->setClientData($actionInitDTO->clientData);
        $this->setGetData($actionInitDTO->getData);
        if ($actionInitDTO->objectProperties !== null) {
            $this->setObjectProperties($actionInitDTO->objectProperties);
        }
        if ($this instanceof ControlIdInterface) {
            $this->setControlId($actionInitDTO->eventId);
        }
        if ($actionInitDTO->cell !== null) {
            //TODO: also for tabs
            $this->initLocaleBaseToken($actionInitDTO->cell);
        }
        if ($this instanceof ConfirmAction && $actionInitDTO->eventType !== '') {
            $this->setEventType($actionInitDTO->eventType);
            $this->setObjectValue($actionInitDTO->objectValue);
        }
        if ($this instanceof ExportInterface && $actionInitDTO->id !== null) {
            $exportAction = clone $this;
            $exportAction->setEventId($actionInitDTO->id, $actionInitDTO->eventId);
            return $exportAction;
        }
        foreach ($this->nested as $nestedAction) {
            $nestedAction->initializeAction($actionInitDTO);
        }
        return $this;
    }

    public function getActionInitDTO(): ?ActionInitDTO
    {
        return $this->actionInitDTO;
    }

    public function getEventContainer(): ?EventContainerInterface
    {
        return $this->eventContainer;
    }

    public function setObjectProperties(array $objectProperties): void
    {
        $this->objectProperties = $objectProperties;
    }

    public function getObjectProperties(): array
    {
        return $this->objectProperties;
    }

    public function setClientTimeZone(?DateTimeZone $clientTimeZone): void
    {
        if ($clientTimeZone !== null) {
            $this->clientTimeZone = $clientTimeZone;
            if (!empty($this->nested)) {
                foreach ($this->nested as $action) {
                    if ($action instanceof Action) {
                        $action->setClientTimeZone($clientTimeZone);
                    }
                }
            }
        }
    }

    protected function getClientTimeZone(): ?DateTimeZone
    {
        return $this->clientTimeZone ?? null;
    }

    /**
     * @param Action ...$actions
     * @return $this
     */
    public function addAction(Action ...$actions): self
    {
        foreach ($actions as $action) {
            if (!in_array($action, $this->nested)) {
                $this->nested[] = $action;
            }
        }
        return $this;
    }

    abstract protected function runAction(): ActionResultInterface;

    /**
     * @return array
     */
    public function getResult(): array
    {
        if ($this->checkRunConditions() === true) {
            $result = $this->runAction()->getResultArray($this->actionInitDTO?->cell?->getNewId() ?? null);
            if ($this->runNested === true) {
                $mergeArray = [];
                foreach ($this->nested as $action) {
                    if ($action instanceof Action) {
                        $mergeArray[] = $action->getResult();
                    }
                }
                $result = array_merge_recursive($result, ...$mergeArray);
            }
            //TODO: make actions (and everything) return a result object instead of an array
            if (!array_key_exists('state', $result)) {
                $result['state'] = HttpResponseState::SUCCESS->value;
            }
            if (is_array($result['state'])) {
                $result['state'] = min($result['state']);
            }
            return $result;
        }
        return [];
    }

    protected function getLegacyId()
    {
        return $this->actionInitDTO->legacyId;
    }

    /**
     * @param bool $bool
     * @return $this
     */
    public function setRunNested(bool $bool = true): self
    {
        $this->runNested = $bool;
        return $this;
    }

    public function setClientData(?ClientData $clientData): void
    {
        $this->clientData = $clientData;
    }

    public function setGetData(?GetData $getData): void
    {
        $this->getData = $getData;
    }

    protected function getGetData(): ?GetData
    {
        return $this->getData;
    }

    protected function getClientData(): ?ClientData
    {
        return $this->clientData;
    }

    public function initLocaleBaseToken(Cell $cell): void
    {
        $this->localeBaseToken = $cell->createLocaleBaseToken('Cell');
    }

    /**
     * @param string $eventType
     * @internal
     */
    public function setEventType(string $eventType): void
    {
        $this->eventType = $eventType;
    }

    /**
     * @return string
     * @internal
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * @param array $cellNames
     * @param ID\ID|null $containerId
     * @return Cell[]
     */
    protected function getCells(array $cellNames, ?ID\ID $containerId = null): array
    {
        $cells = [];
        if ($containerId === null) {
            $containerId = $this->actionInitDTO->cell?->getNewId();
        }
        if ($containerId?->isTabId() === true) {
            foreach ($cellNames as $cellName) {
                $cells[] = Session::getCell(ID\ID::refactor($cellName, $containerId));
            }
        }
        return array_values(array_filter($cells));
    }

    /**
     * @API
     */
    public function setPermission(string|UnitEnum ...$permissions): self
    {
        foreach ($permissions as $permission) {
            if ($permission instanceof UnitEnum) {
                $this->permissions[$permission->name] = $permission->name;
            } else {
                $this->permissions[$permission] = $permission;
            }
        }
        return $this;
    }

    /**
     * @param Closure $callable the condition callback has to return a boolean
     * @param ...$args
     * @return $this
     */
    public function condition(Closure $callable, ...$args): self
    {
        $this->conditionCallback = $callable;
        $this->conditionArgs     = $args;
        return $this;
    }

    /**
     * @API
     * @param string $class
     * @param string $staticMethod
     * @param ...$args
     * @return $this
     */
    public function staticCallback(string $class, string $staticMethod, ...$args): self
    {
        $this->staticCallback['class']  = $class;
        $this->staticCallback['method'] = $staticMethod;
        $this->staticCallback['args']   = $args;
        return $this;
    }

    public function checkRunConditions(): bool
    {
        if (!empty($this->permissions)) {
            $permissionAccessType[] = 0;
            foreach ($this->permissions as $permission) {
                $permissionAccessType[] = Session::getPermissionAccessType($permission);
            }
            if (max($permissionAccessType) === 0) {
                return false;
            }
        }
        if (isset($this->conditionCallback)) {
            return ($this->conditionCallback)(...$this->conditionArgs);
        }
        if (isset($this->staticCallback)) {
            if (class_exists($this->staticCallback['class'])) {
                if (method_exists($this->staticCallback['class'], $this->staticCallback['method'])) {
                    $class  = $this->staticCallback['class'];
                    $method = $this->staticCallback['method'];
                    return $class::$method(...$this->staticCallback['args']);
                }
                \byteShard\Debug::warning('staticCallback method '.$this->staticCallback['method'].' does not exist in class '.$this->staticCallback['class']);
                return false;
            }
            \byteShard\Debug::warning('staticCallback: class '.$this->staticCallback['class'].' does not exist');
            return false;
        }
        return true;
    }

    public function getNestedActions(): array
    {
        return $this->nested;
    }
}
