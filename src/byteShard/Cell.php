<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Form\Control\Upload;
use byteShard\Form\FormInterface;
use byteShard\ID\IDElement;
use byteShard\ID\UploadId;
use byteShard\Internal\Action;
use byteShard\Internal\Cell\Storage;
use byteShard\Internal\CellInterface;
use byteShard\Internal\ContainerInterface;
use byteShard\Internal\ContentClassFactory;
use byteShard\Internal\Event\Event;
use byteShard\Internal\Event\EventStorage;
use byteShard\Internal\Event\EventStorageInterface;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\Session;
use byteShard\Internal\Struct;
use byteShard\Internal\Toolbar\ToolbarContainer;
use byteShard\Tree\TreeInterface;
use byteShard\Utils\Strings;
use UnitEnum;

/**
 * Class Cell
 */
class Cell implements CellInterface, EventStorageInterface, ContainerInterface, ToolbarContainer
{
    use PermissionImplementation {
        setPermission as PermissionTrait_setPermission;
        setAccessType as PermissionTrait_setAccessType;
    }
    use EventStorage;

    public const HEIGHT    = 'CellHeight';
    public const WIDTH     = 'CellWidth';
    public const COLLAPSED = 'Collapsed';

    private string $containerId = '';
    /** @var array */
    private array $toolbar = [];
    /** @var Storage[] */
    private array $storage = [];
    /** @var Struct\GetData|null */
    private ?Struct\GetData $getData = null;
    /** @var array */
    private array $event = [];
    /** @var array<string, Event> */
    private array $contentEvents = [];
    /** @var array */
    private array $confirmations = [];
    /** @var string */
    private static string $cellNamespace = "\\App\\Cell\\";
    private string        $nonce         = '';
    private ?ID\ID        $selectedId    = null;
    private string        $layoutCellId  = '';
    private ?ID\ID        $id            = null;
    private string        $actionId;
    private string        $cssClass      = '';
    private string        $contentFormat = 'XML';
    private string        $clickedLinkId;

    private ?string $refactorCellId       = null;
    private ?string $namespace            = null;
    private ?string $collapsedLabel       = null;
    private bool    $registered           = false;
    private bool    $userWidth            = false;
    private ?int    $width                = null;
    private bool    $userHeight           = false;
    private ?int    $height               = null;
    private bool    $hideHeader           = false;
    private bool    $hideArrow            = false;
    private bool    $useFixedHeight       = false;
    private bool    $useFixedWidth        = false;
    private ?string $originalContentClass = null;
    private ?string $localeName           = null;
    private ?string $name                 = null;
    private bool    $collapsed            = false;

    private ?string $requestTimestamp = null;
    private array   $controls         = [];
    private array   $encrypted        = [];
    private array   $toolbarListId    = [];
    private ?string $filterValue      = null;
    private string  $visibleDateRange;
    private array   $nestedControls   = [];
    private array   $uploads          = [];

    public function __construct(private string $contentClass = '')
    {

    }

    public function containerId(): string
    {
        return $this->getNewId()->getEncryptedContainerId();
    }

    public function cellId(): string
    {
        return $this->getNewId()->getEncryptedCellIdForEvent();
    }

    /**
     * add a css class to the layoutCell
     * @param string $class
     * @return $this
     */
    public function setCssClass(string $class): self
    {
        $this->cssClass = $class;
        return $this;
    }

    /**
     * @param $id
     * @return array
     * @internal
     */
    public function closeConfirmationPopup($id): array
    {
        $result['state'] = 0;
        if (array_key_exists($id, $this->confirmations)) {
            $result = $this->confirmations[$id]->closeConfirmationPopup();
            unset($this->confirmations[$id]);
        }
        return $result;
    }

    //###############################################################
    // Setter
    //###############################################################

    /**
     * This will set the initial width of the cell which can later be changed by the user (unless otherwise specified)
     * On any subsequent login the width is currently evaluated from the cookie
     * On any subsequent reload the width is evaluated from the framework
     * The width will only be set the first time this method is called.
     *
     * @param int $int
     * @return $this
     */
    public function setWidth(int $int): self
    {
        if ($this->userWidth === false) {
            $this->width = $int;
        }
        return $this;
    }

    /**
     * @param int $int
     * @return $this
     * @internal store the cell width in the session after the user resized a cell
     */
    public function setWidthOnResize(int $int): self
    {
        $this->width = $int;
        return $this;
    }

    /**
     * This will set the initial height of the cell which can later be changed by the user (unless otherwise specified)
     * On any subsequent login the height is currently evaluated from the cookie
     * On any subsequent reload the height is evaluated from the framework
     * The height will only be set the first time this method is called.
     *
     * @param int $int
     * @return $this
     */
    public function setHeight(int $int): self
    {
        if ($this->userHeight === false) {
            $this->height = $int;
        }
        return $this;
    }

    /**
     * @param int $height
     * @return $this
     * @internal store the cell height in the session after the user resized a cell
     */
    public function setHeightOnResize(int $height): self
    {
        $this->height = $height;
        return $this;
    }

    /**
     * @return string
     */
    public function getScopeLocaleToken(): string
    {
        return $this->createLocaleBaseToken('Cell');
    }

    /**
     * This will hide the header row of a cell
     * Note: collapse / expand buttons and meta information in certain CellContents will not be available
     *
     * @param bool|true $bool
     * @return $this
     */
    public function setHideHeader(bool $bool = true): self
    {
        $this->hideHeader = $bool;
        return $this;
    }

    /**
     * @return $this
     * @API
     */
    public function setHideArrow(): self
    {
        $this->hideArrow = true;
        return $this;
    }

    /**
     * This will make the cell not resizable horizontally
     * (works only in a Layout with at least 2 columns and at least one column must be auto sizable)
     *
     * @param bool|true $bool
     * @return $this
     * @API
     */
    public function setFixedWidth(bool $bool = true): self
    {
        $this->useFixedWidth = $bool;
        return $this;
    }

    /**
     * This will make the cell not resizable vertically
     * (works only in a Layout with at least 2 rows and at least one row must be auto sizable)
     *
     * @param bool|true $bool
     * @return $this
     * @API
     */
    public function setFixedHeight(bool $bool = true): self
    {
        $this->useFixedHeight = $bool;
        return $this;
    }

    public function setNonce(): string
    {
        $this->nonce = Crypto::randomBytes(24);
        return $this->nonce;
    }

    //###############################################################
    // Getter
    //###############################################################

    public function getNonce(): string
    {
        return $this->nonce;
    }

    //###############################################################
    // Framework internal functions
    //###############################################################

    /**
     * @param string $cell
     * @return string
     */
    public static function getContentCellName(string $cell): string
    {
        $lower = strtolower($cell);
        if (str_starts_with($lower, 'app\\cell')) {
            $cell = substr($cell, 8);
        } elseif (str_starts_with($lower, '\\app\\cell')) {
            $cell = substr($cell, 9);
        }
        return str_replace(ltrim(self::$cellNamespace, '\\'), '', ltrim($cell, '\\'));
    }

    public static function isFormContent(string $cell): bool
    {
        $class = trim(self::$cellNamespace.trim(str_replace(ltrim(self::$cellNamespace, '\\'), '', ltrim($cell, '\\')), '\\'), '\\');
        return is_subclass_of($class, FormInterface::class);
    }

    public static function isTreeContent(string $cell): bool
    {
        $class = trim(self::$cellNamespace.trim(str_replace(ltrim(self::$cellNamespace, '\\'), '', ltrim($cell, '\\')), '\\'), '\\');
        return is_subclass_of($class, TreeInterface::class);
    }

    /**
     * @param string $className
     * @param string $checkType
     * @param string $callerClassMethod
     * @return string
     * @throws Exception
     */
    static public function getContentClassName(string $className, string $checkType = '', string $callerClassMethod = ''): string
    {
        if ($checkType !== '') {
            $namespacedClassName = trim(self::$cellNamespace.trim(str_replace(ltrim(self::$cellNamespace, '\\'), '', ltrim($className, '\\')), '\\'), '\\');
            $calledIn            = null;
            switch ($checkType) {
                case 'Grid':
                    $gridClass = ContentClassFactory::getGridClass();
                    if (!is_subclass_of($namespacedClassName, $gridClass, true)) {
                        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                        if (array_key_exists(2, $trace) && array_key_exists('class', $trace[2])) {
                            $calledIn = $trace[2]['class'];
                        }
                        if ($callerClassMethod === '') {
                            throw new Exception(__METHOD__.': cell '.$namespacedClassName.' must be of type '.$gridClass.($calledIn !== null ? ' (called in '.$calledIn.')' : ''), 106800008);
                        }
                        throw new Exception($callerClassMethod.': cell '.$namespacedClassName.' must be of type '.$gridClass.($calledIn !== null ? ' (called in '.$calledIn.')' : ''), 10680009);
                    }
                    break;
                case 'Form':
                    if (!is_subclass_of($namespacedClassName, FormInterface::class)) {
                        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                        if (array_key_exists(2, $trace) && array_key_exists('class', $trace[2])) {
                            $calledIn = $trace[2]['class'];
                        }
                        if ($callerClassMethod === '') {
                            throw new Exception(__METHOD__.': cell '.$namespacedClassName.' must be of type '.FormInterface::class.($calledIn !== null ? ' (called in '.$calledIn.')' : ''), 106800010);
                        }
                        throw new Exception($callerClassMethod.': cell '.$namespacedClassName.' must be of type '.FormInterface::class.($calledIn !== null ? ' (called in '.$calledIn.')' : ''), 106800011);
                    }
                    break;
                case 'Tree':
                    if (!is_subclass_of($namespacedClassName, TreeInterface::class)) {
                        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                        if (array_key_exists(2, $trace) && array_key_exists('class', $trace[2])) {
                            $calledIn = $trace[2]['class'];
                        }
                        if ($callerClassMethod === '') {
                            throw new Exception(__METHOD__.': cell '.$namespacedClassName.' must be of type '.TreeInterface::class.($calledIn !== null ? ' (called in '.$calledIn.')' : ''), 106800012);
                        }
                        throw new Exception($callerClassMethod.': cell '.$namespacedClassName.' must be of type '.TreeInterface::class.($calledIn !== null ? ' (called in '.$calledIn.')' : ''), 106800013);
                    }
                    break;
            }
        }
        return str_replace(ltrim(self::$cellNamespace, '\\'), '', ltrim($className, '\\'));
    }

    //###############################################################
    // Deprecated
    //###############################################################

    /**
     * @param Event $event
     * @return $this
     */
    public function registerContentEvent(Event $event): self
    {
        $name = $event->getContentEventName();
        if (array_key_exists($name, $this->contentEvents)) {
            $this->contentEvents[$name]->addActions(...$event->getActionArray());
        } else {
            $this->contentEvents[$name] = $event;
        }
        return $this;
    }

    /**
     * @param string $eventName
     * @return Action[]
     */
    public function getContentActions(string $eventName): array
    {
        if (array_key_exists($eventName, $this->contentEvents)) {
            $event = $this->contentEvents[$eventName];
            if ($event instanceof Event) {
                return $event->getActionArray();
            }
        }
        return [];
    }

    /**
     * @param string $objectName
     * @param bool $encrypt
     * @param string $encryptedId
     * @return array
     */
    public function getEventIDForInteractiveObject(string $objectName, bool $encrypt = true, string $encryptedId = ''): array
    {
        $result = [];
        // interactive Object is in this cell
        if (isset($this->event['EventIDs'], $this->event['EventIDs'][$objectName])) {
            // Object with that name already registered in this cell, return the ID
            $result['name']       = $this->event['EventIDs'][$objectName];
            $result['registered'] = true;
            return $result;
        }
        // Object not yet registered, generate ID
        $objectIDCounter = 1;
        if (isset($this->event['EventIDCounter'])) {
            // At least one interactive object already registered, get the current object counter
            $objectIDCounter = $this->event['EventIDCounter'];
        }
        // Generate Object ID
        if ($encryptedId === '') {
            if ($encrypt === true) {
                $objectID = ID::getID('Event_ID', $objectIDCounter);
            } else {
                $objectID = $objectIDCounter;
            }
        } else {
            $objectID = $encryptedId;
        }
        // Save Object ID in Tab Object to keep track of registered interactive objects
        $this->event['EventIDs'][$objectName] = $objectID;
        // Increment object counter
        $objectIDCounter++;
        // Save Object counter
        $this->event['EventIDCounter'] = $objectIDCounter;
        $result['name']                = $objectID;
        $result['registered']          = false;
        return $result;
    }

    public function getIDForEvent(string $eventName): mixed
    {
        try {
            $decrypted = \byteShard\Session::decrypt($eventName);
            try {
                $object = json_decode($decrypted);
                if (is_object($object)) {
                    if (property_exists($object, 'i')) {
                        return $object->i;
                    } elseif (property_exists($object, 'id')) {
                        return $object->id;
                    }
                }
                return $object;
            } catch (\Exception) {
                return $decrypted;
            }
        } catch (\Exception) {
        }
        if (isset($this->event['EventIDs']) && is_array($this->event['EventIDs']) && !empty($this->event['EventIDs'])) {
            foreach ($this->event['EventIDs'] as $objectId => $eventId) {
                if ($eventId === $eventName) {
                    return $objectId;
                }
            }
        }
        return null;
    }

    /**
     * @param string $id
     * @return string
     */
    public function getEventNameForID(string $id): string
    {
        $result = '';
        if (isset($this->event['EventIDs'], $this->event['EventIDs'][$id])) {
            $result = $this->event['EventIDs'][$id];
        }
        return $result;
    }

    /**
     * @param string $name
     */
    public function setContentClassName(string $name): void
    {
        if ($this->contentClass !== '' && $this->originalContentClass === null) {
            $this->originalContentClass = $this->contentClass;
        }
        $this->contentClass = $name;
    }

    public function revertCustomContentClassName(): void
    {
        $this->contentClass = $this->originalContentClass ?? '';
    }

    /**
     *
     */
    public function resetEvents(): void
    {
        $this->event = [];
    }

    /**
     * @param Struct\GetData $dataObject
     * @internal
     */
    public final function setGetDataActionClientData(Struct\GetData $dataObject): void
    {
        $this->getData = $dataObject;
    }

    /**
     * @return Struct\GetData|null
     * @internal
     */
    public final function getGetDataActionClientData(): ?Struct\GetData
    {
        if ($this->getData instanceof Struct\GetData) {
            return $this->getData;
        }
        return null;
    }

    /**
     * store client request time to detect database update concurrency
     */
    public function setRequestTimestamp(): void
    {
        $this->requestTimestamp = (string)microtime(true);
    }

    /**
     * get time of last client request
     * @return ?float
     */
    public function getRequestTimestamp(): ?float
    {
        if ($this->requestTimestamp !== null) {
            return (float)$this->requestTimestamp;
        }
        return null;
    }

    /**
     * @return array|null
     */
    public function getFormFieldUploadData(): ?array
    {
        $result = null;
        foreach ($this->controls as $encryptedName => $field) {
            if (isset($field['objectType']) && $field['objectType'] === Upload::class) {
                $result[$encryptedName] = $field;
            }
        }
        return $result;
    }

    /**
     * This function is used to register cell content fields in the session. Those fields will be fetched upon client update
     * Only register form fields, grid columns which are relevant for updates
     * no labels
     * TODO: check if form buttons are relevant
     *
     * @session write
     * @param string $encryptedName encrypted name which will be returned by the client
     * @param string $name internal name, usually maps to the database field
     * @param int $accessType 0, 1, 2 (Enum\AccessType enum)
     * @param ?string $columnType database column type aka varchar, int etc. (Enum\DB\ColumnType)
     * @param ?string $objectType Form\Control\Input or Grid\Column\Calendar etc
     * @param null|string $label name/column-name that is displayed in the client. Might be used for generic error messages / warnings
     * @param array $validations validations on that object like min length
     * @param ?string $dateFormat the format the client returns a date in
     * @param ?string $encryptedRadioValue the encrypted value of a radio control
     * @param ?string $radioValue the value of a radio control
     * @internal
     */
    public function setContentControlType(string $encryptedName, string $name, int $accessType, ?string $columnType = null, ?string $objectType = null, ?string $label = null, array $validations = [], ?string $dateFormat = null, ?string $encryptedRadioValue = null, ?string $radioValue = null, bool $encryptedValue = false): void
    {
        // reverse lookup
        $this->encrypted[$name] = $encryptedName;
        // object data
        $this->controls[$encryptedName]['name']       = $name; //name is used in bs_post
        $this->controls[$encryptedName]['accessType'] = $accessType;
        if ($columnType !== null) {
            $this->controls[$encryptedName]['type'] = $columnType;
        }
        if ($objectType !== null) {
            $this->controls[$encryptedName]['objectType'] = $objectType; // used in DataHarmonizer and ModifyFormObject
        }
        if ($label !== null) {
            $this->controls[$encryptedName]['label'] = $label;
        }
        if (!empty($validations)) {
            $this->controls[$encryptedName]['validations'] = $validations;
        }
        if ($dateFormat !== null) {
            $this->controls[$encryptedName]['date_format'] = $dateFormat; // used in DataHarmonizer
        }
        if ($radioValue !== null) {
            $this->controls[$encryptedName]['radio_value'][$encryptedRadioValue] = $radioValue; // used in ModifyFormObject
        }
        $this->controls[$encryptedName]['encryptedValue'] = $encryptedValue;
    }

    /**
     * this is currently only used for combo boxes.
     * might be used for radio buttons as well?
     * @param $encryptedName
     * @param $id
     * @internal
     */
    public function setContentSelectedID($encryptedName, $id): void
    {
        $this->controls[$encryptedName]['selected_id'] = $id;
    }

    public function setVisibleDateRange(string $range): void
    {
        $this->visibleDateRange = $range;
    }

    public function getVisibleDateRange(): string
    {
        return $this->visibleDateRange ?? '';
    }

    public function getContentSelectedID(?string $name): mixed
    {
        if ($name !== null && $name !== '' && isset($this->encrypted[$name], $this->controls[$this->encrypted[$name]], $this->controls[$this->encrypted[$name]]['selected_id'])) {
            return $this->controls[$this->encrypted[$name]]['selected_id'];
        }
        return null;
    }

    /**
     * This function is used to get the content object fields from the session.
     * Those fields are fetched upon client update
     *
     * @session read
     * @return array
     * @internal
     */
    public function getContentControlType(): array
    {
        return $this->controls;
    }

    public function setUploadedFileInformation(array $file): void
    {
        $this->uploads[$file['id']] = $file;
    }

    /**
     * This method is used to register nested controls. This is currently only used for Form Radios.
     *
     * @session write
     * @param $parentName
     * @param $value
     * @param array $nestedNames
     * @internal
     */
    public function setNestedControls($parentName, $value, array $nestedNames): void
    {
        foreach ($nestedNames as $name) {
            $this->nestedControls[$parentName][$value][$name] = $name;
        }
    }

    /**
     * This method returns nested controls. This is currently only used for Form Radios.
     *
     * @session read
     * @return array
     * @internal
     */
    public function getNestedControls(): array
    {
        return $this->nestedControls;
    }

    /**
     * clear content types like form_fields, nested_objects or uploaded files
     *
     * @session write
     * @internal
     */
    public function clearContentObjectTypes(): void
    {
        $this->controls       = [];
        $this->nestedControls = [];
        $this->encrypted      = [];
        foreach ($this->uploads as $file) {
            unlink($file['fqfn']);
        }
        $this->uploads = [];
    }

    /**
     * returns the encrypted client name for the internal object name (reverse lookup)
     *
     * @session read
     * @internal
     */
    public function getEncryptedName(string $unencryptedName): ?string
    {
        if (array_key_exists($unencryptedName, $this->encrypted)) {
            return $this->encrypted[$unencryptedName];
        }
        return null;
    }

    /**
     * setID is called when the cell is added to the Layout
     * @param string $id
     * @return $this
     * @internal
     */
    public function setID(string $id): self
    {
        $this->refactorCellId = $id;
        if ($this->registered === false) {
            $this->registered = true;
            $this->setDimensions();
        }
        return $this;
    }

    public function init(string $layoutCellId, ?\byteShard\ID\ID $cellId): void
    {
        $this->layoutCellId = $layoutCellId;
        $this->id           = $cellId;
    }

    public function getEncodedId(): string
    {
        return $this->id->getEncodedCellId(false);
    }

    public function getEncryptedId(): string
    {
        return $this->id->getEncryptedCellId();
    }

    public function getTabName(): string
    {
        return $this->tabId ?? '';
    }

    /**
     * @param string $name
     * @return $this
     * @internal
     */
    public function setName(string $name): self
    {
        trigger_error(__METHOD__.'  is deprecated', E_USER_DEPRECATED);
        $this->name = $name;
        if ($this->registered === false) {
            $this->registered = true;
            $this->setDimensions();
        }
        return $this;
    }

    /**
     * @param string $namespace
     * @return $this
     * @internal
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        if ($this->registered === false) {
            $this->registered = true;
            $this->setDimensions();
        }
        return $this;
    }

    /**
     * get the cell dimensions and collapse state from the session and apply them to the cell
     */
    private function setDimensions(): void
    {
        $size = \byteShard\Session::getSizeData($this->namespace.'\\'.$this->refactorCellId);
        foreach ($size as $type => $val) {
            switch ($type) {
                case self::HEIGHT:
                    $this->setHeight($val);
                    $this->userHeight = true;
                    break;
                case self::WIDTH:
                    $this->setWidth($val);
                    $this->userWidth = true;
                    break;
                case self::COLLAPSED:
                    $this->collapsed = true;
                    break;
            }
        }
    }

    /**
     * @return string
     */
    public function getContentClass(): string
    {
        if ($this->contentClass === '') {
            if ($this->id !== null) {
                $containerId = $this->id->getContainerId();
                if ($containerId !== '') {
                    return 'App\\Container\\'.$containerId;
                }
                return self::$cellNamespace.$this->id->getCellId();
            } else {
                if (empty($this->refactorCellId)) {
                    return '';
                }
                return rtrim(self::$cellNamespace, '\\').'\\'.trim($this->namespace, '\\').'\\'.$this->refactorCellId;
            }
        }
        if (str_starts_with(strtolower($this->contentClass), 'app\\cell')) {
            return $this->contentClass;
        }
        return self::$cellNamespace.$this->contentClass;
    }

    public static function getClassName(\byteShard\ID\ID $id): string
    {
        $containerId = $id->getContainerId();
        if ($containerId !== '') {
            return 'App\\Container\\'.$containerId;
        }
        return self::$cellNamespace.$id->getCellId();
    }

    public function __toString()
    {
        return $this->getContentClass();
    }

    public function getShortName(): string
    {
        if ($this->contentClass === '') {
            return $this->namespace.'\\'.$this->refactorCellId;
        }
        return trim($this->contentClass, '\\');
    }


    /**
     * @session read
     * @return string
     */
    public function getContentFormat(): string
    {
        return $this->contentFormat;
    }

    /**
     * @param string $format
     * @API
     */
    public function setContentFormat(string $format): void
    {
        $this->contentFormat = $format;
    }

    public function setSelectedID(\byteShard\ID\ID $id): self
    {
        $this->selectedId = $id;
        return $this;
    }

    public function addSelectedIDElements(IDElement ...$elements): self
    {
        if ($this->selectedId instanceof \byteShard\ID\ID) {
            $this->selectedId->addIdElement(...$elements);
        } else {
            $this->selectedId = \byteShard\ID\ID::factory(...$elements);
        }
        return $this;
    }

    public function getSelectedId(): ?\byteShard\ID\ID
    {
        return $this->selectedId;
    }

    /**
     * @param $element_id
     * @param $id
     * @return $this
     */
    public function setToolbarListID($element_id, $id): self
    {
        $this->toolbarListId[$element_id] = $id;
        return $this;
    }

    /**
     * @param string $element
     * @return int|string|mixed|null
     * @API
     */
    public function getToolbarListID(string $element): mixed
    {
        $id = $this->getEventNameForID($element);
        if (array_key_exists($id, $this->toolbarListId)) {
            return $this->toolbarListId[$id];
        }
        return null;
    }

    /**
     * @param string $element
     * @return void
     * @API
     */
    public function unsetToolbarListID(string $element = ''): void
    {
        if ($element === '') {
            $this->toolbarListId = [];
        } else {
            $id = $this->getEventNameForID($element);
            if (array_key_exists($id, $this->toolbarListId)) {
                unset($this->toolbarListId[$id]);
            }
        }
    }

    /**
     * @return $this
     */
    public function unsetSelectedID(): self
    {
        $this->selectedId = null;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return Strings::purify(Locale::get($this->createLocaleBaseToken('Cell').'.Label'));
    }

    public function getNavigationData(Session $session = null): array
    {
        $cellData        = [];
        $cellData['ID']  = $this->id->getEncryptedCellId();
        $cellData['EID'] = $this->id->getEncryptedCellIdForEvent();
        if ($this->collapsedLabel !== null) {
            $cellData['collapsedLabel'] = $this->collapsedLabel;
        }
        if ($this->collapsed === true) {
            $cellData['collapsed'] = true;
        }
        if (!empty($this->toolbar)) {
            $cellData['toolbar'] = true;
        } else {
            $cellData['toolbar'] = false;
        }
        if ($this->width !== null) {
            $cellData['width'] = $this->width;
        }
        if ($this->height !== null) {
            $cellData['height'] = $this->height;
        }
        if ($this->useFixedWidth === true) {
            $cellData['fixSize']['width'] = true;
        }
        if ($this->useFixedHeight === true) {
            $cellData['fixSize']['height'] = true;
        }
        if ($this->hideHeader === true) {
            $cellData['hideHeader'] = true;
            $cellData['label']      = '';
        } else {
            $cellData['label'] = $this->getLabel();
        }
        if ($this->hideArrow === true) {
            $cellData['hideArrow'] = true;
        }
        if ($this->cssClass !== '') {
            $cellData['class'] = $this->cssClass;
        }
        return $cellData;
    }

    /**
     * @param bool $bool
     * @return $this
     */
    public function setCollapsed(bool $bool = true): self
    {
        $this->collapsed = $bool;
        return $this;
    }

    /**
     * @return bool
     */
    public function getHorizontalAutoSize(): bool
    {
        return $this->width === null;
    }

    /**
     * @return bool
     */
    public function getVerticalAutoSize(): bool
    {
        return $this->height === null;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setFilterValue(string $value): self
    {
        $this->filterValue = $value;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getFilterValue(): ?string
    {
        return $this->filterValue;
    }

    /**
     * @session write
     * @deprecated
     * @API
     */
    public function getContainerID(): ?Struct\Navigation_ID
    {
        trigger_error('Method getContainerID is deprecated.', E_USER_DEPRECATED);
        return null;
    }

    /**
     * @session write
     */
    public function getLayoutContainerID(bool $suppressDeprecation = false): ?Struct\Navigation_ID
    {
        trigger_error('getLayoutContainerID is deprecated', E_USER_DEPRECATED);
        return null;
    }

    public function getClientId(): string
    {
        trigger_error('Method getClientId is deprecated.', E_USER_DEPRECATED);
        return '';
    }

    /**
     * @param string $containerId
     * @return $this
     */
    public function setContainerID(string $containerId): self
    {
        $this->containerId = $containerId;
        if ($this->registered === false) {
            $this->registered = true;
            $this->setDimensions();
        }
        return $this;
    }

    /**
     * @session write
     */
    public function getID(): Struct\ID|array|null|string
    {
        if ($this->refactorCellId !== null) {
            return ID::explode($this->refactorCellId);
        }
        return null;
    }

    public function getLayoutCellId(): string
    {
        return $this->layoutCellId;
    }

    public function getNewId(): ?ID\ID
    {
        return $this->id;
    }


    public function getCellId(): ?string
    {
        trigger_error('Method getContainerID is deprecated.', E_USER_DEPRECATED);
        return null;
    }

    /**
     * the name of a cell equals the namespace without leading and trailing slashes and all other slashes replaced by underscores
     * @return string
     */
    public function getName(): string
    {
        if ($this->id->isPopupId()) {
            return str_replace('\\', '_', $this->id->getPopupId());
        }
        return str_replace('\\', '_', $this->id->getTabId());
    }

    public function createLocaleBaseToken(string $type): string
    {
        if ($this->contentClass !== '') {
            return str_replace('\\', '_', trim($this->contentClass, '\\')).'::'.$type.'.'.$this->layoutCellId;
        }
        if (isset($this->id)) {
            if ($this->id->isPopupId() === true) {
                return str_replace('\\', '_', $this->id->getPopupId()).'::'.$type.'.'.$this->layoutCellId;
            }
            return str_replace('\\', '_', $this->id->getTabId()).'::'.$type.'.'.$this->layoutCellId;
        }
        return 'fallback::'.$type.'.'.$this->layoutCellId;
    }

    /**
     * @return string|null
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * @param string $localeName
     * @return Cell
     * @API
     */
    public function setLocaleName(string $localeName): self
    {
        $this->localeName = $localeName;
        return $this;
    }

    /**
     * @return string
     */
    public function getLocaleName(): string
    {
        if ($this->localeName !== null) {
            return $this->localeName;
        }
        return $this->getName();
    }

    /**
     * @return mixed
     */
    public function &getRelatedIDReference(): mixed
    {
        return $this->selectedId;
    }

    /**
     * @param string $collapsedLabel
     * @return $this
     * @API
     */
    public function setCollapsedLabel(string $collapsedLabel): self
    {
        $this->collapsedLabel = $collapsedLabel;
        return $this;
    }

    /**
     * @return string
     * @API
     */
    public function getToolbarClass(): string
    {
        if (!empty($this->toolbar)) {
            if (isset($this->toolbar['name'])) {
                return $this->toolbar['name'];
            }
            return $this->name.'_'.$this->refactorCellId.'_toolbar';
        }
        return '';
    }

    /**
     * @return $this
     */
    public function setPermission(string|UnitEnum ...$permissions): self
    {
        $this->PermissionTrait_setPermission(...$permissions);
        $this->passAccessType();
        return $this;
    }

    /**
     * @param int $accessType
     * @return $this
     */
    public function setAccessType(int $accessType): self
    {
        $this->PermissionTrait_setAccessType($accessType);
        $this->passAccessType();
        return $this;
    }

    /**
     * TODO: check if accessType === 0
     * on 0 remove cell from layout, change pattern accordingly
     */
    private function passAccessType()
    {
    }

    /**
     * @param string $id
     * @param mixed $defaultValue
     * @return Storage
     */
    public function createDataStorage(string $id, mixed $defaultValue): Storage
    {
        if (!array_key_exists($id, $this->storage)) {
            $this->storage[$id] = new Storage($defaultValue);
            return $this->storage[$id];
        }
        return $this->storage[$id];
    }

    /**
     * @param string $id
     * @param mixed $value
     * @return Cell
     */
    public function storeData(string $id, mixed $value): self
    {
        if (array_key_exists($id, $this->storage)) {
            $this->storage[$id]->setValue($value);
        }
        return $this;
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function getStoredData(string $id): mixed
    {
        return ($this->storage[$id] ?? null)?->getValue();
    }

    /**
     * @param string $actionId
     * @return Cell
     * @internal
     */
    public function setActionId(string $actionId): self
    {
        $this->actionId = $actionId;
        return $this;
    }

    /**
     * @return string
     */
    public function getActionId(): string
    {
        return $this->actionId ?? '';
    }
}
