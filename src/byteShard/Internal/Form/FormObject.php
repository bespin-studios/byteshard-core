<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Form;

use byteShard\Cell;
use byteShard\Form\Control\ClosePopupButton;
use byteShard\Enum;
use byteShard\Exception;
use byteShard\Internal\Event\Event;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Locale;
use byteShard\Utils\Strings;
use Closure;

/**
 * Class FormObject
 * @exceptionId 00008
 * @package byteShard\Internal\Form
 */
abstract class FormObject
{
    use PermissionImplementation;

    protected array               $attributes             = [];
    protected array               $nestedControls         = [];
    protected array               $note                   = [];
    protected array               $parameters             = [];
    protected array               $userdata               = [];
    protected bool                $help                   = false;
    protected ?Enum\DB\ColumnType $dbColumnType           = null;
    protected string              $displayedTextAttribute = '';
    protected string              $fontWeight             = '';
    protected string              $placeholder            = '';
    protected string              $token                  = '';
    protected string              $type                   = '';
    protected ?Cell               $cell                   = null;

    private array              $events             = [];
    private array              $localeReplacements = [];
    private string             $localeName         = '';
    private ?Closure           $binding            = null;
    private bool               $bind               = true;
    protected static Enum\Cast $cast               = Enum\Cast::STRING;
    private string             $randomId;
    private string             $objectId;
    private bool               $asynchronous       = false;

    public function isAsynchronous(): bool
    {
        return $this->asynchronous;
    }

    protected function setAsynchronous(bool $asynchronous): self
    {
        $this->asynchronous = $asynchronous;
        return $this;
    }

    /**
     * FormObject constructor.
     * @param null|string $id
     */
    public function __construct(?string $id)
    {
        $this->objectId           = $id ?? '';
        $this->attributes['name'] = $id;
    }

    public function getFormObjectId(): string
    {
        return $this->objectId;
    }

    public static function getCast(): string
    {
        return static::$cast->value;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param Cell $cell
     * @return void
     * @internal
     */
    public function addCell(Cell $cell): void
    {
        $this->cell = $cell;
    }

    public function getName(): string
    {
        if (isset($this->attributes['name']) && !empty($this->attributes['name'])) {
            return $this->attributes['name'];
        }
        if (isset($this->randomId)) {
            return $this->randomId;
        }
        return '';
    }

    public function setRandomNameForObjectsWithoutId(string $name): void
    {
        $this->randomId = $name;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getUserData(): array
    {
        return $this->userdata;
    }

    public function getNestedItems(): array
    {
        return $this->nestedControls;
    }

    public function getHelp(): bool
    {
        return $this->help;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getNote(): array
    {
        return $this->note;
    }

    public function getAttributes(?Cell $cell = null): array
    {
        return $this->attributes;
    }

    public function getFontWeight(): string
    {
        return $this->fontWeight;
    }

    public function getDBColumnType(): ?string
    {
        return $this->dbColumnType?->value ?? null;
    }

    /**
     * @return bool|null
     * @API
     */
    public function getRequired(): ?bool
    {
        if (isset($this->attributes['required'])) {
            return $this->attributes['required'];
        }
        if (array_key_exists(Required::class, class_uses($this))) {
            return false;
        }
        return null;
    }

    public function setLocaleName(string $name): self
    {
        $this->localeName = $name;
        return $this;
    }

    /**
     * add a key / value array / object to replace the %key$s with the value inside the locale
     * @param array|object $replacements
     * @return $this
     * @API
     */
    public function setLocaleReplacements(array|object $replacements): self
    {
        if (is_array($replacements)) {
            $this->localeReplacements = $replacements;
        } elseif (is_object($replacements)) {
            $this->localeReplacements = get_object_vars($replacements);
        }
        return $this;
    }

    /**
     * set the locale path <CELL_NAME>.Cell.<CELL_ID>.Form.
     * @param string $token
     * @internal
     * TODO: this should be moved to the proxy
     */
    public function setLocaleBaseToken(string $token): void
    {
        // store the token to be passed on to nested items
        $this->token = $token;
        $traits      = class_uses($this);

        // check if the object supports setTooltip
        $search_tooltip = array_key_exists(Tooltip::class, $traits);
        // check if the object supports setNote and no Note has been set
        $search_note = array_key_exists(Note::class, $traits) && !(array_key_exists('text', $this->note) && $this->note['text'] !== '');
        $search_info = array_key_exists(Info::class, $traits) && (array_key_exists('info', $this->attributes)) && $this->attributes['info'] === true;

        if ($this->displayedTextAttribute !== '' && array_key_exists('name', $this->attributes) && (array_key_exists($this->displayedTextAttribute, $this->attributes) === false || $this->attributes[$this->displayedTextAttribute] === null)) {
            $name = $this->localeName !== '' ? $this->localeName : $this->attributes['name'];
            if ($this->type === 'radio' && array_key_exists('value', $this->attributes)) {
                $locale = Locale::getArray($token.$name.'.'.$this->attributes['value'].'.Label');
                if ($search_tooltip) {
                    $tooltip = Locale::getArray($token.$name.'.'.$this->attributes['value'].'.Tooltip');
                }
                if ($search_note) {
                    $note = Locale::getArray($token.$name.'.'.$this->attributes['value'].'.Note');
                }
                if ($search_info) {
                    $info = Locale::getArray($token.$name.'.'.$this->attributes['value'].'.Info');
                }
            } else {
                $locale = Locale::getArray($token.$name.'.Label');
                if ($search_tooltip) {
                    $tooltip = Locale::getArray($token.$name.'.Tooltip');
                }
                if ($search_note) {
                    $note = Locale::getArray($token.$name.'.Note');
                }
                if ($search_info) {
                    $info = Locale::getArray($token.$name.'.Info');
                }
                if (array_key_exists(Placeholder::class, $traits)) {
                    if ($this->placeholder === 'bs_locale') {
                        $placeholder = Locale::getArray($token.$name.'.Placeholder');
                        if ($placeholder['found'] === true) {
                            $this->userdata['bs_placeholder'] = $placeholder['locale'];
                        }
                    } elseif ($this->placeholder !== '') {
                        $this->userdata['bs_placeholder'] = $this->placeholder;
                    }
                }
            }

            if ($locale['found'] === true) {
                $this->attributes[$this->displayedTextAttribute] = !empty($this->localeReplacements) ? Strings::replace($locale['locale'], $this->localeReplacements) : $locale['locale'];
            } else {
                $this->attributes[$this->displayedTextAttribute] = '';
            }
            if ($search_tooltip && $tooltip['found'] === true && method_exists($this, 'setTooltip')) {
                $this->setTooltip(!empty($this->localeReplacements) ? Strings::replace($tooltip['locale'], $this->localeReplacements) : $tooltip['locale']);
            }
            if ($search_note && $note['found'] === true && method_exists($this, 'setNote')) {
                $this->setNote(Strings::purify(!empty($this->localeReplacements) ? Strings::replace($note['locale'], $this->localeReplacements) : $note['locale']));
            }
            if ($search_info && $info['found'] === true) {
                $this->userdata['bs_info'] = Strings::purify(!empty($this->localeReplacements) ? Strings::replace($info['locale'], $this->localeReplacements) : $info['locale']);
            }
            if ($this->help === true) {
                $help = Locale::getArray($token.$name.'.Help');
                if ($help['found'] === true) {
                    $this->userdata['bs_help'] = Strings::purify(!empty($this->localeReplacements) ? Strings::replace($help['locale'], $this->localeReplacements) : $help['locale']);
                } elseif (defined('DEBUG') && DEBUG === true && defined('DEBUG_LOCALE_TOKEN') && DEBUG_LOCALE_TOKEN === true) {
                    $this->userdata['bs_help'] = Strings::purify($token.$name.'.Help');
                }
            }
        }
        if ($this instanceof ClosePopupButton) {
            $this->attributes['name'] = 'close';
        }
    }

    public function addEvents(Event ...$events): static
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

    public function setDBColumnType(?Enum\DB\ColumnType $enumDBColumnType): self
    {
        $this->dbColumnType = $enumDBColumnType;
        return $this;
    }

    /**
     * @param Closure $binding
     * @return $this
     * @API
     */
    public function setDataBinding(Closure $binding): self
    {
        $this->binding = $binding;
        return $this;
    }

    public function getDataBinding(): ?Closure
    {
        return $this->binding;
    }

    public function __toString()
    {
        return $this->getName();
    }

    /*********************************************
     * Getters with properties defined in traits *
     *********************************************/

    public function getValidations(): array
    {
        if (isset($this->validations)) {
            return $this->validations;
        }
        return [];
    }

    public function getNewValidations(): array
    {
        if (isset($this->newValidations)) {
            return $this->newValidations;
        }
        return [];
    }

    public function getOptions(): array
    {
        if (isset($this->options)) {
            return $this->options;
        }
        return [];
    }
}
