<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Action\ConfirmAction;
use byteShard\Cell;
use byteShard\Enum;
use byteShard\Environment;
use byteShard\Exception;
use byteShard\File\FileInterface;
use byteShard\File\Text;
use byteShard\Form\FormInterface;
use byteShard\Form\FormSettingsInterface;
use byteShard\ID;
use byteShard\ID\CellIDElement;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Cell\Storage;
use byteShard\Internal\Event\Event;
use byteShard\Internal\Event\EventMigrationInterface;
use byteShard\Internal\Export\ExportInterface;
use byteShard\Internal\Export\HandlerInterface;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\Ribbon\RibbonClassInterface;
use byteShard\Internal\Ribbon\RibbonObjectInterface;
use byteShard\Internal\Struct\ClientCell;
use byteShard\Internal\Struct\ClientCellComponent;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Internal\Struct\Navigation_ID;
use byteShard\Locale;
use byteShard\Popup\Message;
use byteShard\Ribbon\RibbonInterface;
use byteShard\Scheduler;
use byteShard\Session;
use byteShard\Toolbar\ToolbarInterface;
use byteShard\Toolbar\ToolbarObjectInterface;
use DateTimeZone;
use stdClass;

/**
 * Class CellContent
 * @package byteShard\Internal
 */
abstract class CellContent implements ContainerInterface, ExportInterface
{
    use PermissionImplementation {
        setPermission as PermissionTrait_setPermission;
        setAccessType as PermissionTrait_setAccessType;
    }

    // overwrite in child:
    protected string               $cellContentType;
    protected Cell                 $cell;
    protected ?string              $filterValue   = null;
    protected stdClass             $user;
    protected ?int                 $user_id;
    protected ?string              $username;
    protected ToolbarInterface     $toolbar;
    protected RibbonClassInterface $ribbon;
    private string                 $outputCharset = 'UTF-8';
    protected string               $locale;
    private ?string                $cellHeader    = null;
    private array                  $idCache       = [];
    private array                  $events        = [];
    protected ?ClientData          $clientData;
    protected ?ID\ID               $selectedID;
    protected ?Struct\GetData      $getDataID;
    private DateTimeZone           $clientTimeZone;
    private CellContent            $fallbackContent;

    /**
     * TODO: OPTIMIZE: constructor too long... several actions create an instance of cell content and need only very few of it
     * CellContent constructor.
     * @param Cell $cell
     * @throws Exception
     */
    public function __construct(Cell $cell, private ?string $context)
    {
        $this->locale         = Session::getLocale();
        $this->user_id        = Session::getUserId();
        $this->username       = Session::getUsername();
        $this->user           = new stdClass();
        $this->user->userId   = $this->user_id;
        $this->user->username = $this->username;
        $additionalUserData   = Session::getAdditionalUserdata();
        foreach ($additionalUserData as $key => $val) {
            $this->user->{$key} = $val;
        }

        $this->cell = $cell;
        $this->setParentAccessType($cell->getAccessType());
        // figure out why and what -> lunchtime
        $this->filterValue = $cell->getFilterValue();
        if ($this instanceof Scheduler) {
            $this->selectedID = $cell->getSelectedId() ?? null;
        } else {
            $this->selectedID = $cell->getSelectedId();
        }
        $this->getDataID = $cell->getGetDataActionClientData();
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function getContext(): string
    {
        return $this->context ?? '';
    }

    public function __get(string $name): ?array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $hint      = '';
        $file      = $backtrace[0]['file'] ?? null;
        if ($file !== null) {
            $hint = ' (Called in file: '.$file;
            $line = $backtrace[0]['line'] ?? null;
            if ($line !== null) {
                $hint .= ' line: '.$line;
            }
            $hint .= ')';
        }
        if (str_starts_with($name, 'related')) {
            trigger_error('accessing property '.$name.' is deprecated. Please use getID() instead.'.$hint, E_USER_DEPRECATED);
        } else {
            trigger_error('accessing property '.$name.' is deprecated.'.$hint, E_USER_DEPRECATED);
        }
        return match ($name) {
            'relatedID', 'relatedTabID', 'relatedLayoutID' => [],
            default                                        => null,
        };
    }

    /**
     * @param Event ...$events
     * @return $this
     * @api
     * @session none
     */
    public function addEvents(Event ...$events): self
    {
        foreach ($events as $event) {
            if (!in_array($event, $this->events, true)) {
                $this->events[] = $event;
            }
        }
        return $this;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    protected function getParentEventsForClient(): array
    {
        $events = [];
        foreach ($this->events as $event) {
            if ($event instanceof EventMigrationInterface) {
                $events = array_merge_recursive($events, $event->getClientArray($this->cell->getNonce()));
            }
        }
        foreach ($events as $eventName => $callbackFunctions) {
            if (is_array($callbackFunctions)) {
                $events[$eventName] = array_unique($callbackFunctions);
            }
        }
        return $events;
    }


    public function getNewId(): ?ID\ID
    {
        return $this->cell->getNewId();
    }

    /**
     * @param ?DateTimeZone $timeZone
     * @return void
     * @internal
     */
    public function setClientTimeZone(?DateTimeZone $timeZone): void
    {
        if ($timeZone !== null) {
            $this->clientTimeZone = $timeZone;
        }
    }

    public function getClientTimeZone(): ?DateTimeZone
    {
        return $this->clientTimeZone ?? null;
    }

    public function getOutputCharset(): string
    {
        return $this->outputCharset;
    }

    public static function setSelectedId(ID\ID $id)
    {
        $cellId = ID\ID::factory(new CellIDElement(get_called_class()), new TabIDElement(Session::legacyGetSelectedTab()));
        Session::getCell($cellId)?->setSelectedID($id);
    }

    /**
     * @API
     * @param string $cellHeader
     * @return $this
     */
    protected function setCellHeader(string $cellHeader): self
    {
        $this->cellHeader = $cellHeader;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getCellHeader(): ?string
    {
        return $this->cellHeader;
    }

    /**
     * get the user id of the current user
     * @return int
     */
    public function getUserID(): int
    {
        return $this->user->userId;
    }

    /**
     * get the username of the current user
     * @return string
     */
    public function getUsername(): string
    {
        return $this->user->username;
    }

    /**
     * get the current locale (e.g. 'en')
     * @return string
     * @API
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return string
     * @API
     * @deprecated use getScopeLocaleToken() instead
     */
    public function getBaseLocale(): string
    {
        return $this->getScopeLocaleToken();
    }

    /**
     * @return string
     */
    public function getScopeLocaleToken(): string
    {
        return $this->cell->createLocaleBaseToken('Cell');
    }

    protected function getScopeLocaleTokenBasedOnNamespace(string $type = 'Cell'): string
    {
        $namespace = str_replace('App\\Cell\\', '', get_class($this));
        $parts = explode('\\', $namespace);
        $cell = array_pop($parts);
        return implode('_', $parts).'::'.$type.'.'.$cell;
    }

    /**
     * @param $id
     * @return array
     * @API
     */
    public function closeConfirmationPopup($id): array
    {
        return $this->cell->closeConfirmationPopup($id);
    }

    /**
     * @deprecated
     */
    public function getContainerID(): ?Navigation_ID
    {
        trigger_error('Method getContainerID is deprecated.', E_USER_DEPRECATED);
        return null;
    }

    /**
     * @deprecated
     */
    public function getLayoutContainerID(): ?Navigation_ID
    {
        trigger_error('Method getLayoutContainerID is deprecated.', E_USER_DEPRECATED);
        return null;
    }

    public function getClientId(): string
    {
        trigger_error('Method getClientId is deprecated.', E_USER_DEPRECATED);
        return '';
    }


    /**
     * getID()                                       returns current cell id
     * getID('Project_ID')                           returns Project_ID from current cell
     * getID('Project_ID', 'project\\a')             returns Project_ID from cell project\\a
     * getID('Project_ID', 'project\\a', 'selected') returns selected Project_ID from cell project\\a
     * @param string|null $id
     * @param string|null $namespace
     * @param string|null $type
     * @return Struct\ID|int|null|string|\DateTime
     */
    public function getID(?string $id = null, ?string $namespace = null, ?string $type = null): Struct\ID|int|null|string|\DateTime
    {
        if ($id === null && $type !== 'date') {
            return $this->cell->getID();
        }
        if ($namespace !== null) {
            $tabId = $this->cell->getNewId()?->getTabId();
            if (empty($tabId)) {
                $tabId = Session::legacyGetSelectedTab();
            }
            $cellId = ID\ID::factory(new ID\TabIDElement($tabId), new ID\CellIDElement($namespace));
            $cellId->getEncodedCellId(false);
            $targetCell = Session::getCell($cellId);
        } else {
            $targetCell = $this->cell;
        }
        if ($targetCell instanceof Cell) {
            if ($type === 'date') {
                return $targetCell->getSelectedId()?->getSelectedDate();
            } elseif ($id !== null) {
                return $targetCell->getSelectedId()?->getId($id) ?? null;
            }
        }
        return null;
    }

    private object $dragged;

    public function getDragged(): ?object
    {
        return $this->dragged ?? null;
    }

    public function setDragged(object $dragged): void
    {
        $this->dragged = $dragged;
    }

    /**
     * @param string $permission
     * @return int
     * @API
     */
    public function getPermissionAccessType(string $permission): int
    {
        return Session::getPermissionAccessType($permission);
    }

    public function getCell(): Cell
    {
        return $this->cell;
    }

    /**
     * @return string
     */
    public function getContentClass(): string
    {
        return $this->cell->getContentClass();
    }

    public function getCellContent(bool $resetNonce = true): ?ClientCell
    {
        if ($resetNonce === true) {
            $this->cell->setNonce();
        }
        $this->cell->resetEvents();
        self::applyGlobalFormSettings($this);
        return null;
    }

    private static function applyGlobalFormSettings(CellContent $cellContent): void
    {
        if ($cellContent instanceof FormInterface) {
            global $env;
            if ($env instanceof Environment) {
                $settings = $env->getFormSettings();
                if ($settings instanceof FormSettingsInterface) {
                    $cellContent->addFormSettings($settings);
                }
            }
        }
    }

    public function useFallbackContent(CellContent $cellContent): ?CellContent
    {
        $this->fallbackContent = $cellContent;
        return null;
    }

    public function hasFallbackContent(): bool
    {
        return isset($this->fallbackContent);
    }

    public function getFallbackContent(): CellContent
    {
        self::applyGlobalFormSettings($this->fallbackContent);
        $this->fallbackContent->setClientTimeZone($this->getClientTimeZone());
        return $this->fallbackContent;
    }

    /**
     * @return array<ClientCellComponent|ContentComponent>
     * @throws Exception
     */
    protected function getComponents(string $nonce = ''): array
    {
        $result = [];
        if (method_exists($this, 'defineToolbarContent')) {
            $this->toolbar = ContentClassFactory::getToolbar($this->cell);
            $this->defineToolbarContent();
            $toolbar = $this->toolbar->getComponent();
            if ($toolbar !== null) {
                $result[] = $toolbar;
            }
        } elseif ($this instanceof RibbonInterface) {
            $this->ribbon = ContentClassFactory::getRibbon($this->cell);
            $this->defineRibbonContent();
            $ribbon = $this->ribbon->getComponent();
            if ($ribbon !== null) {
                $result[] = $ribbon;
            }
        }
        return $result;
    }

    /**
     * @param ?string $contentId
     * @return array
     */
    public function getXlsExport(?string $contentId): array
    {
        //$msg = new Message('test');
        //return $msg->getNavigationArray();
        return $this->defineXlsExport();
    }

    /**
     * @param $filename
     * @param $content_id
     */
    public function getPptExport($filename, $content_id)
    {
    }

    /**
     * @param ConfirmAction $confirmationId
     * @return array
     */
    public function getConfirmationDialogue(ConfirmAction $confirmationId): array
    {
        $definedMessage = $this->defineConfirmationDialogue($confirmationId);
        if (is_array($definedMessage)) {
            return $definedMessage;
        }
        return [];
    }

    public function setProcessedClientData(?ClientData $clientData): void
    {
        if ($clientData !== null) {
            $this->clientData = $clientData;
        }
    }

    /**
     * cell content specific contents like column types or field permissions must be checked in their respective classes
     * here cell access type and defineUpdate method existence are verified
     *
     * @param ClientData $clientData
     * @return array|null
     */
    public function runClientUpdate(ClientData $clientData): ?array
    {
        if (method_exists($this, 'defineUpdate')) {
            // validation ok, set validated data as clientData and run method defineUpdate which needs to be defined in the respective cell
            $this->clientData = $clientData;
            $result           = $this->defineUpdate();
            if (is_array($result)) {
                return $result;
            }
            if ($result instanceof \byteShard\Internal\Action) {
                return [$result];
            }
            if ($result !== null) {
                // this would indicate an invalid return value like a string or a bool
                return null;
            }
            return [];
        }
        $msg = new Message(Locale::get('byteShard.cellContent.undefined_method'));
        return $msg->getNavigationArray();
    }

    /**
     * @return array|bool
     * @API
     */
    protected function preCheckUpdate(): array|bool
    {
        if ($this->getAccessType() !== Enum\AccessType::RW) {
            $msg = new Message(Locale::get('byteShard.cellContent.permission.failed'));
            return $msg->getNavigationArray();
        }
        if (method_exists($this, 'defineUpdate') === false) {
            if (defined('DEBUG') && DEBUG === true) {
                $msg = new Message(Locale::get('byteShard.cellContent.method.notFound'));
            } else {
                $msg = new Message(Locale::get('byteShard.cellContent.generic'));
            }
            return $msg->getNavigationArray();
        }
        return true;
    }

    /**
     * @param ToolbarObjectInterface ...$toolbar_objects
     * @return void
     * @API
     */
    protected function addToolbarObject(ToolbarObjectInterface ...$toolbar_objects): void
    {
        if (isset($this->toolbar)) {
            $this->toolbar->addToolbarObject(...$toolbar_objects);
        }
    }

    /**
     * @param int $accessType
     * @return $this
     * @API
     */
    protected function setToolbarAccessType(int $accessType): self
    {
        if (isset($this->toolbar)) {
            if (method_exists($this->toolbar, 'setAccessType')) {
                $this->toolbar->setAccessType($accessType);
            }
        }
        return $this;
    }

    protected function addRibbonObject(RibbonObjectInterface ...$ribbonObject): void
    {
        if (isset($this->ribbon)) {
            $this->ribbon->addRibbonObject(...$ribbonObject);
        }
    }

    protected function setRibbonAccessType(int $accessType): self
    {
        if (isset($this->ribbon)) {
            $this->ribbon->setAccessType($accessType);
        }
        return $this;
    }

    /**
     *
     */
    protected function defineCellContent()
    {
    }

    /**
     *
     */
    protected function defineDataBinding()
    {
    }

    /**
     * @return array
     */
    protected function defineXlsExport(): array
    {
        return ['format' => [], 'content' => []];
    }

    /**
     *
     */
    protected function definePptExport()
    {
    }

    /**
     * @return FileInterface
     * @internal
     */
    public function defineDownloadParent(): FileInterface
    {
        $download = $this->defineDownload();
        if ($download !== null) {
            return $download;
        }
        $result = new Text();
        $result->setContent('to define your download, you must return an object which implements FileInterface');
        $result->setName('download.txt');
        return $result;
    }

    /**
     * override method in the cell content when you want to provide a file to be downloaded
     * return an array with the following indexes
     * file_content
     * file_name
     * content_type
     */
    protected function defineDownload(): ?FileInterface
    {
        return null;
    }

    /**
     * This method is only called if no message is defined in the ConfirmAction constructor
     *
     * return an array with one these optional keys:
     * string 'message': if message is defined it will be displayed instead of the default locale
     *
     * bool 'show_confirmation':
     *      true: show the confirmation dialogue
     *        default locale token: <Class>.Cell.<CellID>.Action.ConfirmAction.<ConfirmationID>.Label
     *      false: show a notice e.g. confirmation conditions not met
     *        default locale token: <Class>.Cell.<CellID>.Action.ConfirmAction.<ConfirmationID>.noConfirmation.Label
     *
     * string|array|Object 'locale_replacements': will be passed with vksprintf into the locale string
     *
     * @param ConfirmAction $confirmAction
     */
    protected function defineConfirmationDialogue(ConfirmAction $confirmAction)
    {
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $id = $this->cell->getNewId()?->getCellId();
        if ($id !== null) {
            return $id;
        }
        return get_class($this);
    }

    /**
     * @return string
     * @API
     */
    public function getActionId(): string
    {
        return $this->cell->getActionId();
    }

    public function getNonce(): string
    {
        return $this->cell->getNonce();
    }

    /**
     * @param string $id
     * @param mixed $defaultValue
     * @return Storage
     * @API
     */
    public function createDataStorage(string $id, mixed $defaultValue): Storage
    {
        return $this->cell->createDataStorage($id, $defaultValue);
    }

    /**
     * @param string $id
     * @param mixed $value
     * @API
     */
    public function storeData(string $id, mixed $value)
    {
        $this->cell->storeData($id, $value);
    }

    /**
     * @param string $id
     * @return mixed
     * @API
     */
    public function getStoredData(string $id): mixed
    {
        return $this->cell->getStoredData($id);
    }

    public function getExportHandler(ExportHandler $exportHandler): ?HandlerInterface
    {
        return null;
    }
}
