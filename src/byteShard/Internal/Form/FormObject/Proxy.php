<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Form\FormObject;

use byteShard\Cell;
use byteShard\Combo;
use byteShard\Enum;
use byteShard\Exception;
use byteShard\Form;
use byteShard\Form\Control;
use byteShard\ID\UploadId;
use byteShard\Internal\Action;
use byteShard\Internal\Debug;
use byteShard\Internal\Event\Event;
use byteShard\Internal\Form\FormObject;
use byteShard\Internal\Form\InputWidth;
use byteShard\Internal\Form\InputWidthInterface;
use byteShard\Internal\Form\Name;
use byteShard\Internal\Form\OnlyReadInterface;
use byteShard\Internal\Form\Options;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\SimpleXML;
use byteShard\Locale;
use byteShard\Session;
use byteShard\Utils\Strings;
use Closure;
use DateTime;
use SimpleXMLElement;

// TODO: connector
// TODO: cssName
// TODO: format

/**
 * Class Proxy
 * @package byteShard\Internal\Form\FormObject
 */
final class Proxy
{
    use PermissionImplementation;
    use Name;
    use Options;
    use InputWidth;

    public array    $attributes     = [];
    private ?string $internalNestedIdentifier;
    private bool    $liveValidation = false;

    private ?string $dbColumnType;
    private array   $events;
    private string  $formObjectType;
    public string   $internalName;

    // used in form
    private array $nestedItems = [];
    //public array $options = [];
    private array $parameters;
    private array $userdata;
    private bool  $comboAllowsNewEntries = false;

    /**
     * if any form object uses dependency validations the dhtmlx _validate anonymous function will be replaced
     * @var bool
     */
    private bool $hasDependencyValidation = false;

    /**
     * if any form input uses placeholders, the placeholder attribute will be evaluated on every input
     * @var bool
     */
    private bool $hasPlaceholder = false;

    private array $note;
    public array  $validation;

    private ?Form\Validation $deprecatedValidation = null;

    private string  $uploadMethod;
    private array   $uploadFileTypes        = [];
    private string  $uploadTargetFilename   = '';
    private string  $uploadTargetPath       = '';
    private bool    $uploadClearAfterUpload = false;
    private ?string $clientName;
    private ?string $selectedComboOption    = null;

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    private string  $encryptedValue;
    private mixed   $unencryptedValue;
    private bool    $info = false;
    public ?Closure $binding;
    private string  $token;

    /**
     * @var bool
     */
    private bool   $applyDefaultInputWidth = false;
    private bool   $encryptOptionValues    = false;
    private bool   $showHelp               = false;
    private string $cellNonce;
    private string $id;
    private string $clientLabel;
    private array  $objectProperties       = [];
    private bool   $asynchronous;
    private bool   $singleFileMode         = false;

    /**
     * Proxy constructor.
     * @param FormObject $formObject
     * @param Cell $cell
     * @param int $parentAccessType
     * @param string|null $defaultInputWidth
     * @param string $nonce
     * @param string $parentName internal parameter
     * @param null $parentValue
     */
    public function __construct(FormObject $formObject, Cell $cell, int $parentAccessType, ?string $defaultInputWidth = null, string $nonce = '', string $parentName = '', $parentValue = null)
    {
        $this->cellNonce    = $nonce;
        $this->asynchronous = $formObject->isAsynchronous();
        if ($formObject instanceof Control\Upload) {
            $this->singleFileMode = $formObject->getSingleFileMode();
        }
        $this->initializeProxyWithAttributesOfFormObject($formObject, $cell);
        $this->initializeValidations($formObject);
        $this->initializeAccessType($formObject, $parentAccessType);
        $this->initializeInputWidth($formObject, $defaultInputWidth);
        $this->initializeFontWeight($formObject);
        $this->processUserData($formObject);
        $this->setObjectId($formObject);
        $this->setClientLabel($formObject);

        $this->clientName         = self::getEncryptedClientName($this->getObjectId($formObject), $nonce);
        $this->objectProperties[] = self::getProperties($formObject, $this->getObjectId($formObject), $this->getClientLabel(), $this->getAccessType());
        if ($formObject instanceof Control\Combo) {
            $this->selectedComboOption = $formObject->getSelectedClientOption() !== null ? Session::encrypt($formObject->getSelectedClientOption(), $nonce) : null;
        }

        $this->controlTypeSpecificImplementation($formObject);
        $this->createNestedProxies($formObject, $cell, $defaultInputWidth, $nonce, $parentName, $parentValue);
    }

    private function getObjectId(FormObject $formObject): string
    {
        return $this->id;
    }

    private function setObjectId(FormObject $formObject): void
    {
        $this->id = $formObject->getName();
        if (empty($this->id)) {
            // newColumn, Block etc. are usually empty
            $this->id = md5(random_bytes(64));
        }
    }

    public function getComboAllowsNewEntries(): bool
    {
        return $this->comboAllowsNewEntries;
    }

    private function getObjectNonce(string $objectId): string
    {
        return substr(md5($this->cellNonce.$objectId), 0, 24);
    }

    public static function getProperties(FormObject $formObject, string $name, string $label, int $accessType): object
    {
        $formObjectClass = $formObject::class;
        if (str_starts_with($formObjectClass, 'byteShard\\Form\\Control\\')) {
            $formObjectClass = '!f'.substr($formObjectClass, 23);
        }
        if ($name === '') {
            $name = $formObject->getName();
        }
        // i = ID
        // a = AccessRight
        // t = objectType
        // v = validations
        // c = cast
        // l = label
        // d = allow invalid value decoding
        $properties = [
            'i' => $name,
            't' => $formObjectClass,
        ];
        if ($accessType !== Enum\AccessType::READWRITE) {
            $properties['a'] = $accessType;
        }
        $cast = $formObject::getCast();
        if ($cast !== 's') {
            $properties['c'] = $cast;
        }

        if ($formObject instanceof Control\Combo && $formObject->getAllowNewEntries()) {
            $properties['d'] = true;
        }

        $validations = $formObject->getNewValidations();
        if (!empty($validations)) {
            // radio elements need the same id
            // todo: add radio label into properties value
            if ($label !== '' && $formObject::class !== Control\Radio::class) {
                $properties['l'] = $label;
            }
            $mergedValidations = [];
            foreach ($validations as $validation) {
                $mergedValidations[] = $validation->getClientArray();
            }
            $properties['v'] = array_merge_recursive(...$mergedValidations);
        } else {
            if ($formObject::class === Control\Combo::class) {
                if ($label !== '' && $formObject->getAllowNewEntries() === false) {
                    $properties['l'] = $label;
                }
            }
        }
        return (object)$properties;
    }

    public static function getEncryptedClientName(string $name, string $cellNonce): string
    {
        $encrypted = [
            'i' => $name
        ];

        // the nonce should be unique per object, but we need to be able to recreate it for object access in actions.
        // The solution is to take a part of the stored nonce and add the object name, generate a md5 from this and use the first 24 characters
        // The nonce will be unique per client rendering as the cell nonce is recycled whenever content is reloaded.
        // That way we can manipulate objects of an existing client form, but we're also in compliance with security recommendations
        $nonce = substr(md5($cellNonce.$name), 0, 24);
        return Session::encrypt(json_encode($encrypted), $nonce);
    }

    private function processUserData(FormObject $formObject): void
    {
        if ($formObject instanceof Control\ClosePopupButton) {
            $this->userdata['clientClose'] = true;
        }
        $this->hasPlaceholder = array_key_exists('bs_placeholder', $this->userdata);
        if ($formObject instanceof Control\Calendar) {
            if (isset($this->attributes['className']) && !empty($this->attributes['className'])) {
                $this->userdata['calendarClass'] = $this->attributes['className'];
            }
            if ($formObject->isOnlyMonthSelector() === true) {
                if (isset($this->userdata['calendarClass'])) {
                    $this->userdata['calendarClass'] .= ' monthOnly';
                } else {
                    $this->userdata['calendarClass'] = 'monthOnly';
                }
            }
        }
    }

    private function initializeValidations(FormObject $formObject): void
    {
        $formValidations = $formObject->getValidations();
        if (!empty($formValidations)) {
            $validationAttributes = [];
            foreach ($formValidations as $validation) {
                $validationAttributes[] = $validation->getClientValidation();
                $userdata               = $validation->getUserData();
                foreach ($userdata as $key => $value) {
                    $this->userdata[$key]          = $value;
                    $this->hasDependencyValidation = true;
                }
            }
            $this->attributes['validate'] = implode(',', array_filter($validationAttributes));
            $this->validation             = $formValidations;
            $this->liveValidation         = true;
        }
    }

    private function initializeInputWidth(FormObject $formObject, $defaultInputWidth): void
    {
        // evaluate if the object supports the input width parameter
        // if yes, check if no manual input width has been defined
        if ($formObject instanceof InputWidthInterface && $formObject->getInputWidth() === null) {
            if ($defaultInputWidth === 'auto') {
                $defaultInputWidth = null;
            } else {
                $defaultInputWidth = (int)$defaultInputWidth;
            }
            $this->setInputWidth($defaultInputWidth);
        }
    }

    private function initializeProxyWithAttributesOfFormObject(FormObject $formObject, Cell $cell): void
    {
        $attributes = $formObject->getAttributes($cell);

        $this->attributes         = $attributes;
        $this->attributes['type'] = $formObject->getType();
        $this->userdata           = $formObject->getUserData();
        $this->liveValidation     = isset($attributes['required']);
        $this->options            = $formObject->getOptions();
        $this->info               = (array_key_exists('info', $attributes) && $attributes['info'] === true);
        $this->showHelp           = $formObject->getHelp();
        $this->internalName       = $formObject->getName();
        $this->token              = $formObject->getToken();
        $this->binding            = $formObject->getDataBinding();
        $this->formObjectType     = get_class($formObject);
        $this->note               = $formObject->getNote();
        $this->dbColumnType       = $formObject->getDBColumnType();
        $this->events             = $formObject->getEvents();
        $this->parameters         = $formObject->getParameters();
    }

    private function initializeFontWeight(FormObject $formObject): void
    {
        $fontWeight = $formObject->getFontWeight();
        switch ($fontWeight) {
            case 'normal':
                $this->addCssClassToObject('bs_font_weight_normal');
                break;
            case 'bold':
                $this->addCssClassToObject('bs_font_weight_bold');
                break;
        }
    }

    private function initializeAccessType(FormObject $formObject, int $parentAccessType): void
    {
        $this->setParentAccessType($parentAccessType);
        $this->setUnrestrictedAccess($formObject->getUnrestrictedAccess());
        $this->setAccessType($formObject->getAccessType());

        if ($this->getAccessType() === Enum\AccessType::R && $formObject instanceof OnlyReadInterface) {
            $this->attributes['readonly'] = true;
        }
        if (isset($this->attributes['readonly'], $this->attributes['required']) && $this->attributes['readonly'] === true || $this->getAccessType() === Enum\AccessType::R && isset($this->attributes['required'])) {
            // if a field is readonly, it cannot be required at the same time
            unset($this->attributes['required']);
            $this->liveValidation = false;
        }
        if ($this->getAccessType() === Enum\AccessType::R && isset($this->attributes['type']) && $this->attributes['type'] === 'button') {
            // buttons don't use 'readonly', they are disabled instead
            unset($this->attributes['readonly']);
            $this->attributes['disabled'] = true;
        }
    }


    private function createNestedProxies(FormObject $formObject, Cell $cell, ?string $defaultInputWidth, string $nonce, string $parentName, $parentValue): void
    {
        $nestedItems = $formObject->getNestedItems();
        if (!empty($nestedItems)) {
            $name                     = $this->getObjectId($formObject);
            $internalNestedIdentifier = ($parentName !== '' && $parentValue !== null) ? $parentName.$parentValue.$name : $formObject->getName();
            foreach ($nestedItems as $nestedItem) {
                // set locale token is used for the label or button text and needs to be set for nested items as well
                if ($nestedItem instanceof Control\Combo) {
                    $nestedItem->setSelectedId($cell->getContentSelectedID($nestedItem->getName()));
                }
                $nestedItem->setLocaleBaseToken($formObject->getToken());
                if (!array_key_exists('value', $this->attributes) || empty($this->attributes['value'])) {
                    $proxy = new Proxy($nestedItem, $cell, $this->getAccessType(), $defaultInputWidth, $nonce);
                } else {
                    $proxy = new Proxy($nestedItem, $cell, $this->getAccessType(), $defaultInputWidth, $nonce, $internalNestedIdentifier, $this->attributes['value']);
                }
                $this->objectProperties = array_merge($this->objectProperties, $proxy->getObjectProperties());
                $this->nestedItems[]    = $proxy;
            }
        }
    }

    public function getObjectProperties(): array
    {
        return $this->objectProperties;
    }

    private function addCssClassToObject(string $class): void
    {
        $classes[]                     = $this->attributes['className'] ?? '';
        $classes[]                     = $class;
        $this->attributes['className'] = implode(' ', array_filter($classes));
    }

    private function controlTypeSpecificImplementation(FormObject $formObject): void
    {
        if ($formObject instanceof Control\Link) {
            $this->attributes['value']  = $formObject->getValue();
            $this->attributes['format'] = $formObject->getFormat();
        } elseif ($formObject instanceof Control\Combo) {
            if ($this->getAccessType() === Enum\AccessType::R) {
                $this->attributes['disabled'] = true;
            }
            $this->encryptOptionValues   = $formObject->getEncryptOptionValues();
            $this->comboAllowsNewEntries = $formObject->getAllowNewEntries();
        } elseif ($formObject instanceof Control\Upload) {
            $this->attributes['url']      = $formObject->getUrl();
            $this->uploadMethod           = $formObject->getMethod();
            $this->uploadFileTypes        = $formObject->getFileTypes();
            $this->uploadTargetFilename   = $formObject->getTargetFilename();
            $this->uploadTargetPath       = $formObject->getTargetPath();
            $this->uploadClearAfterUpload = $formObject->getClearAfterUpload();
            $this->dbColumnType           = 'form_upload';
        } elseif ($formObject instanceof Control\Calendar) {
            $dateLocale = (isset($this->attributes['enableTime']) && $this->attributes['enableTime'] === true) ? 'date_time' : 'date';
            if (empty($this->attributes['serverDateFormat'])) {
                $this->attributes['serverDateFormat'] = Locale::get('byteShard.date.form.'.$dateLocale.'.server');
            }
            if (empty($this->attributes['dateFormat'])) {
                $this->attributes['dateFormat'] = Locale::get('byteShard.date.form.'.$dateLocale.'.client');
            }
            //TODO: test different date / datetime formats
            $this->attributes['value'] = $formObject->getValue(str_replace('%', '', $this->attributes['serverDateFormat']));
        }
    }

    private function setClientLabel(FormObject $formObject): void
    {
        if ($formObject instanceof Control\Button) {
            $attribute = 'value';
            if (!isset($this->attributes[$attribute])) {
                $this->attributes[$attribute] = '';
            }
        } else {
            $attribute = 'label';
        }
        if (isset($this->attributes[$attribute]) && !empty($this->attributes[$attribute])) {
            $label                        = Strings::purify($this->attributes[$attribute]);
            $this->clientLabel            = $label;
            $this->attributes[$attribute] = $label;
        }
    }

    private function setClientLabelOld(FormObject $formObject): void
    {
        if ($formObject instanceof Control\Button) {
            $attribute = 'value';
        } else {
            $attribute = 'label';
        }
        $label = '';
        if (isset($this->attributes[$attribute]) && !empty($this->attributes[$attribute])) {
            $label = Strings::purify($this->attributes[$attribute]);
        }
        $this->clientLabel            = $label;
        $this->attributes[$attribute] = $label;
    }

    private function getClientLabel(): string
    {
        return $this->clientLabel ?? '';
    }

    public function getNestedItems(): array
    {
        return $this->nestedItems;
    }

    public function setValueFromData($value): void
    {
        switch ($this->formObjectType) {
            case Control\Label::class:
                $this->attributes['label'] = sprintf($this->attributes['label'], $value);
                break;
            case Control\Input::class:
            case Control\Textarea::class:
                $this->attributes['value'] = $value;
                break;
            case Control\Checkbox::class:
                $this->attributes['checked'] = $value == 1;
                break;
            case Control\Combo::class:
                if ($value instanceof Combo\Option) {
                    $this->setOptions($value);
                } elseif (is_array($value)) {
                    $options = [];
                    foreach ($value as $option) {
                        if ($option instanceof Combo\Option) {
                            $options[] = $option;
                        } elseif (is_object($option) && isset($option->value, $option->text)) {
                            $selected = false;
                            if (isset($option->selected)) {
                                $selected = (bool)$option->selected;
                            }
                            $options[] = new Combo\Option($option->value, $option->text, $selected);
                        }
                    }
                    $this->setOptions($options);
                }
                break;
            default:
                Debug::debug(__METHOD__.': Found formObject Name in Queried Data, but type not specified ('.$this->formObjectType.')');
                break;
        }
        if (array_key_exists('text', $this->note)) {
            $this->note['text'] = sprintf($this->note['text'], $value);
        }
    }

    /**
     * @return null|string
     */
    public function getDateFormat(): ?string
    {
        switch ($this->formObjectType) {
            case Form\Control\Calendar::class:
                if (array_key_exists('enableTime', $this->attributes) && $this->attributes['enableTime'] === true) {
                    return Locale::get('byteShard.date.form.date_time.object');
                }
                return Locale::get('byteShard.date.form.date.object');
        }
        return null;
    }

    public function processFormControlsInComboOptions(Cell $cell, int $accessType): array
    {
        $result = [];
        foreach ($this->options as $optionKey => $option) {
            if ($option instanceof Combo\Option && !empty($option->nestedItems)) {
                $nestedNames = [];

                foreach ($option->nestedItems as $key => $nestedObject) {
                    if ($nestedObject instanceof FormObject) {
                        $nestedProxy                                  = new Proxy($nestedObject, $cell, $accessType, null, $this->internalName, $option->getValue());
                        $result[]                                     = $nestedProxy;
                        $nestedNames[]                                = $nestedProxy->getClientName();
                        $this->options[$optionKey]->nestedItems[$key] = $nestedProxy;
                    }
                }
                $cell->setNestedControls($this->getClientName(), $option->getValue(), $nestedNames);
                unset($nestedNames);
            }
        }
        return $result;
    }

    /**
     * @return array
     */
    public function getValidation(): array
    {
        if (isset($this->validation)) {
            $result = [];
            foreach ($this->validation as $validation) {
                $result[$validation->getRule()] = $validation->getValue();
            }
            return $result;
        }
        if ($this->deprecatedValidation !== null) {
            return $this->deprecatedValidation->getValidationArray();
        }
        return [];
    }

    public function setUploadUrlType($type): void
    {
        if (!str_contains($this->attributes['url'], '?')) {
            // no parameters set, just add 'type' parameter
            $this->attributes['url'] = $this->attributes['url'].'?type='.$type;
        } elseif (!str_contains($this->attributes['url'], '?type')) {
            // some url parameters already defined, add type parameter
            $this->attributes['url'] = $this->attributes['url'].'&type='.$type;
        } else {
            $exception = new Exception(__METHOD__.': GET attribute "type" already declared in BSFormUpload URL. Type is reserved by the byteShard framework and is automatically set. '."\n".'Probable solution: remove ?type from upload url');
            $exception->setLocaleToken('byteShard.form.formObject.proxy.setUploadUrlType.duplicate_declaration');
            throw $exception;
        }
    }

    public function getJsonArray(): array
    {
        $json = [];
        foreach ($this->attributes as $name => $value) {
            $json[$name] = $value;
        }
        if (!empty($this->userdata)) {
            $json['userdata'] = $this->userdata;
        }
        foreach ($this->nestedItems as $item) {
            $json['list'][] = $item->getJsonArray();
        }
        return $json;
    }

    /**
     * @param ?SimpleXMLElement $xmlParent
     */
    public function getXMLElement(?SimpleXMLElement $xmlParent): void
    {
        if ($xmlParent === null) {
            return;
        }
        $item = $xmlParent->addChild('item');
        // add attributes to xml element
        foreach ($this->attributes as $name => $value) {
            SimpleXML::addAttribute($item, $name, $value);
        }
        // add note to xml element
        if (array_key_exists('text', $this->note)) {

            $note = SimpleXML::addChild($item, 'note', $this->note['text']);
            if (array_key_exists('width', $this->note)) {
                SimpleXML::addAttribute($note, 'width', $this->note['width']);
            }
        }
        // add userdata to xml element
        foreach ($this->userdata as $name => $userdata) {
            SimpleXML::addAttribute(SimpleXML::addChild($item, 'userdata', $userdata), 'name', $name);
        }
        // add options to combo xml element
        foreach ($this->options as $option) {
            if ($option instanceof Combo\Option) {
                $option->getXMLElement($item, true, $this->encryptOptionValues, $this->cellNonce);
            } else {
                if (is_object($option)) {
                    // convert object to array for unified option handling
                    $option = (array)$option;
                }
                if (is_array($option)) {
                    // convert array keys to lowercase for unified option handling
                    $option = array_change_key_case($option, CASE_LOWER);
                    if (isset($option['value'])) {
                        $xmlOption = $item->addChild('option');
                        SimpleXML::addAttribute($xmlOption, 'value', $option['value']);
                        SimpleXML::addAttribute($xmlOption, 'text', ($option['text'] ?? ''));
                        if (isset($option['selected']) && $option['selected']) {
                            $xmlOption->addAttribute('selected', '1');
                        }
                        if (isset($option['image'])) {
                            SimpleXML::addAttribute($xmlOption, 'img_src', $option['image']);
                            if (!isset($item->attributes()['comboType'])) {
                                $item->addAttribute('comboType', 'image');
                            }
                        }
                    }
                }
            }
        }
        // add nested items to xml element
        foreach ($this->nestedItems as $nestedObject) {
            /* @var $nestedObject Proxy */
            $nestedObject->getXMLElement($item);
        }
    }

    /**
     * @return null|string
     */
    public function getFormObjectName(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    public function encryptValue(): void
    {
        $this->encryptedValue      = Session::encrypt($this->attributes['value']);
        $this->unencryptedValue    = $this->attributes['value'];
        $this->attributes['value'] = $this->encryptedValue;
    }

    /**
     * @param Cell $cell
     * @return FormAlterations
     * @throws Exception
     */
    public function register(Cell $cell): FormAlterations
    {
        $formAlterations = new FormAlterations($this->parameters, $this->selectedComboOption, $this->asynchronous);
        if (array_key_exists('clientClose', $this->userdata)) {
            $formAlterations->addEvent('event_on_close_button_click');
        }
        if ($this->info === true) {
            $formAlterations->addEvent('event_on_info');
        }
        if ($this->hasDependencyValidation === true) {
            //todo: this is no event
            $formAlterations->addEvent('has_dependency_validation');
        }
        if ($this->singleFileMode === true) {
            //todo: this is no event
            $formAlterations->addEvent('use_single_file_mode');
        }
        if ($this->hasPlaceholder === true) {
            //todo: this is no event
            $formAlterations->addEvent('has_placeholders');
        }
        if ($this->formObjectType === Control\Radio::class) {
            $this->encryptValue();
        }

        if ($this->getAccessType() === Enum\AccessType::RW) {
            if ($this->liveValidation === true) {
                //todo: this is no event
                $formAlterations->addEvent('liveValidation');
            }
            if (!empty($this->events)) {
                $tmpName          = $cell->getEventIDForInteractiveObject($this->getFormObjectName(), true, $this->clientName);
                $this->clientName = $tmpName['name'];
                $this->setName($tmpName['name']);
                foreach ($this->events as $event) {
                    //TODO: reimplement client execution. somehow...
                    /* @var $event Event */
                    /*$actions = $event->getActionArray();
                    foreach ($actions as $action) {
                        $action->initActionInCell($cell);
                        if ($action instanceof Action\ClientExecutionInterface && $action->getClientExecution() === true) {
                            $method = $action->getClientExecutionMethod();
                            if ($method !== '') {
                                $formAlterations->addClientExecution($event->getEventType(), $this->clientName, $method, $action->getClientExecutionItems($cell->getNewId()));
                            }
                        }
                    }*/
                    if ($event instanceof Form\Event\OnInputChange) {
                        $formAlterations->addEvent('event_on_input_change');
                        $this->userdata['getAllDataOnChange'] = $event->getGetAllFormObjects();
                    }
                    if ($event instanceof Form\Event\OnButtonClick) {
                        $formAlterations->addEvent('event_on_button_click');
                    }
                    if (($event instanceof Form\Event\OnChange) || ($event instanceof Form\Event\OnCheck) || ($event instanceof Form\Event\OnUnCheck)) {
                        $this->userdata['actionOnChange'] = true;
                        if (($event instanceof Form\Event\OnCheck) || ($event instanceof Form\Event\OnUnCheck)) {
                            $this->userdata[$event->getEventType()] = true;
                        }
                        $formAlterations->addEvent('event_on_change');
                        //TODO: test if all on change usages can be used with this new option and make it the default
                        $this->userdata['getAllDataOnChange'] = (($event instanceof Form\Event\OnChange) && $event->getGetAllFormObjects() === true);
                    }
                    if ($event instanceof Form\Event\OnBlur) {
                        $formAlterations->addEvent('event_on_blur');
                    }
                }
            } else {
                $this->setName($this->clientName);
            }
            if ($this->formObjectType === Control\Upload::class) {
                $formAlterations->addEvent('event_on_upload_file');
                $uploadId = UploadId::getUploadId($cell, $this->clientName, $this->uploadFileTypes, $this->uploadMethod, $this->uploadTargetFilename, $this->uploadTargetPath, $this->uploadClearAfterUpload);
                $this->setUploadUrlType($uploadId);
            }
            if (empty($this->options) && $this->formObjectType === Control\Combo::class) {
                $formAlterations->setOptions(true);
            }
        } else {
            $this->setName($this->clientName);
        }
        if ($this->getUnrestrictedAccess() === true) {
            foreach ($this->events as $event) {
                if ($event instanceof Form\Event\OnButtonClick) {
                    //todo: this is no event
                    $formAlterations->addEvent('eventOnUnrestrictedButtonClick');
                    break;
                }
            }
        }
        if ($this->showHelp === true) {
            $formAlterations->addEvent('event_on_show_help');
            $formAlterations->setHelpObject($this->clientName);
        }

        switch ($this->formObjectType) {
            case Control\Link::class:
                break;
            case Control\Block::class:
                $cell->setContentControlType(encryptedName: $this->clientName, name: $this->internalName, accessType: $this->getAccessType(), objectType: $this->formObjectType, label: ($this->attributes['label'] ?? null), dateFormat: $this->getDateFormat());
                break;
            case Control\ClosePopupButton::class:
                //case Control\Label::class: // Label is needed to disable/enable/hide/show form object
                //case 'Form\Control\ButtonWithOnClickEvent': // check if Button is needed for validation
                //case 'BSFormButton': // check if button is needed for validation
                break;
            case Control\Radio::class:
                $nested_items = [];
                foreach ($this->nestedItems as $nested_form_object) {
                    if ($nested_form_object->clientName !== '') {
                        $nested_items[] = $nested_form_object->clientName;
                    }
                }
                if (!empty($nested_items)) {
                    $cell->setNestedControls($this->clientName, $this->encryptedValue, $nested_items);
                }

                $cell->setContentControlType(encryptedName: $this->clientName, name: $this->internalName, accessType: $this->getAccessType(), objectType: $this->formObjectType, label: ($this->attributes['label'] ?? null), dateFormat: $this->getDateFormat(), encryptedRadioValue: $this->encryptedValue, radioValue: $this->unencryptedValue);
                break;
            default:
                $cell->setContentControlType(encryptedName: $this->clientName, name: $this->internalName, accessType: $this->getAccessType(), objectType: $this->formObjectType, label: ($this->attributes['label'] ?? null), dateFormat: $this->getDateFormat());
                break;
        }
        $formAlterations->setProperties($this->objectProperties);
        $formAlterations->setName($this->getFormObjectName() ?? '');
        return $formAlterations;
    }
}
