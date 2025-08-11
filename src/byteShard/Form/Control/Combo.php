<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Form\Control;

use byteShard\Cell;
use byteShard\Enum;
use byteShard\Form\Enum\ComboType;
use byteShard\Internal\ClientData\EncryptedObjectValueInterface;
use byteShard\Internal\Form;
use byteShard\Session;

/**
 * Class Combo
 * @package byteShard\Form\Control
 */
class Combo extends Form\FormObject implements Form\InputWidthInterface, EncryptedObjectValueInterface, Form\ValueInterface
{
    use Form\ClassName;
    use Form\Connector;
    use Form\ComboType;
    use Form\Disabled;
    use Form\Filtering;
    use Form\Hidden;
    use Form\Info;
    use Form\InputLeft;
    use Form\InputTop;
    use Form\InputWidth;
    use Form\Label;
    use Form\LabelAlign;
    use Form\LabelHeight;
    use Form\LabelLeft;
    use Form\LabelTop;
    use Form\LabelWidth;
    use Form\Name;
    use Form\Note;
    use Form\OffsetLeft;
    use Form\OffsetTop;
    use Form\Options;
    use Form\Position;

    //use Form\OnlyRead;
    use Form\Required;
    use Form\Tooltip;
    use Form\Userdata;
    use Form\Validate;
    use Form\Value;

    protected string              $type                   = 'combo';
    protected string              $displayedTextAttribute = 'label';
    protected ?Enum\DB\ColumnType $dbColumnType           = Enum\DB\ColumnType::BSID_INT_MATCH;
    private bool                  $encryptOptionValues    = false;
    private string                $comboClass             = '';
    private bool                  $allowNewEntries        = false;
    private array                 $comboParameters        = [];
    private array                 $selectedOption         = [];
    private ComboType             $comboType              = ComboType::DEFAULT;
    /**
     * used to store the currently selected if of a combo box. this is only updated if the onChange event is triggered
     * @var mixed
     */
    private mixed $selectedId;

    public function __construct(?string $id, string $comboClass = '')
    {
        parent::__construct($id);
        if ($comboClass !== '' && is_subclass_of($comboClass, \byteShard\Combo::class)) {
            $this->setAsynchronous(true);
            $this->comboClass = $comboClass;
        }
    }

    /**
     * @API
     */
    public function setComboClass(string $comboClass): self
    {
        if (is_subclass_of($comboClass, \byteShard\Combo::class)) {
            $this->setAsynchronous(true);
            $this->comboClass = $comboClass;
        }
        return $this;
    }

    public function getComboClass(): string
    {
        return $this->comboClass;
    }

    public function getAttributes(?Cell $cell = null): array
    {
        $attributes = $this->attributes;
        if ($this->comboClass !== '') {
            $attributes['connector'] = $this->getUrl($cell);
        }
        switch ($this->comboType) {
            case ComboType::IMAGE:
                $attributes['comboType'] = 'image';
                break;
            case ComboType::CHECKBOX:
                $attributes['comboType'] = 'checkbox';
                break;
        }
        return $attributes;
    }

    public function setComboType(ComboType $type): self
    {
        $this->comboType = $type;
        return $this;
    }

    public function getUrl(?Cell $cell): string
    {
        $id['!c'] = $this->comboClass;
        if ($cell !== null) {
            $id['!i'] = $cell->getNewId()?->getEncodedCellId();
        }
        if (!empty($this->comboParameters)) {
            $id['!p'] = serialize($this->comboParameters);
        }
        //TODO: remove these once all consuming combos have been updated
        if (!empty($this->selectedOption)) {
            $id['!s'] = $this->selectedOption;
        }
        return 'bs/bs_combo.php?i='.urlencode(Session::encrypt(json_encode($id)));
    }

    /**
     * @API
     */
    public function setComboParameters(array $parameters): self
    {
        $this->comboParameters = $parameters;
        return $this;
    }

    public function getComboParameters(): array
    {
        return $this->comboParameters;
    }

    /**
     * @param mixed $id
     */
    public function setSelectedID(mixed $id): void
    {
        $this->selectedId = $id;
    }

    /**
     * @param null $id
     * @return mixed
     */
    public function getID($id = null): mixed
    {
        return $this->selectedId;
    }

    /**
     * @API
     */
    public function setReadonly(bool $readonly = true): self
    {
        if ($readonly === true) {
            $this->attributes['readonly'] = true;
            //$this->setAccessType(AccessType::READ);
        } elseif (isset($this->attributes['readonly'])) {
            unset($this->attributes['readonly']);
        }
        return $this;
    }

    /**
     * @API
     */
    public function allowNewEntries(bool $bool = true): self
    {
        $this->allowNewEntries = $bool;
        return $this;
    }

    public function getAllowNewEntries(): bool
    {
        return $this->allowNewEntries;
    }

    /**
     * @API
     */
    public function encryptOptionValues(): self
    {
        $this->encryptOptionValues = true;
        return $this;
    }

    public function getEncryptOptionValues(): bool
    {
        return $this->encryptOptionValues;
    }

    /**
     * key value array like [projectId => 5]
     * @param array<string, string|int> $id
     * @API
     */
    public function setSelectedOption(array $id): self
    {
        $this->selectedOption = $id;
        return $this;
    }

    /**
     * @API
     */
    public function getSelectedOption(): array
    {
        return $this->selectedOption;
    }

    private ?string $selectedClientOption = null;

    public function setSelectedClientOption(string|int|array $optionId): void
    {
        if (is_string($optionId)) {
            $this->selectedClientOption = $optionId;
        } elseif (is_int($optionId)) {
            $this->selectedClientOption = (string)$optionId;
        } else {
            $json = json_encode($optionId);
            if (is_string($json)) {
                $this->selectedClientOption = $json;
            }
        }
    }

    public function getSelectedClientOption(): ?string
    {
        return $this->selectedClientOption;
    }
}
