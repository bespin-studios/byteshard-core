<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Combo;

use byteShard\Internal\Form\FormObject;
use byteShard\Internal\SimpleXML;
use byteShard\Internal\Form\FormObject\Proxy;
use byteShard\ID;
use byteShard\Session;
use SimpleXMLElement;

/**
 * Class BSComboOption
 */
class Option
{
    public array      $nestedItems = [];
    public bool       $hasImage    = false;
    protected bool    $selected    = false;
    protected string  $text        = '';
    protected string  $value       = '';
    protected ?string $image;

    /**
     * Option constructor.
     *
     * @session none
     * @param array|string $value
     * @param null|string $text
     * @param bool $selected
     * @param null|string $image
     */
    public function __construct(array|string $value, ?string $text = null, bool $selected = false, ?string $image = null)
    {
        if (is_array($value)) {
            $values = array();
            foreach ($value as $id => $val) {
                $values[] = ID::getID($id, $val);
            }
            if (count($values) > 0) {
                $this->value = implode(ID::ID_SEPARATOR, $values);
            }
        } else {
            $this->value = $value;
        }
        $this->text     = $text;
        $this->selected = $selected;
        $this->hasImage = !empty($image);
        $this->image    = $image;
    }

    /**
     * @param FormObject ...$formObjects
     * @return $this
     */
    public function addFormObject(FormObject ...$formObjects): self
    {
        foreach ($formObjects as $form_object) {
            $this->nestedItems[] = $form_object;
        }
        return $this;
    }

    /**
     * @param bool $bool
     * @return $this
     */
    public function setSelected(bool $bool = true): self
    {
        $this->selected = $bool;
        return $this;
    }

    /**
     * @return array
     */
    public function getContents(): array
    {
        $result['text']  = $this->text;
        $result['value'] = $this->value;
        if ($this->image !== null) {
            $result['img_src'] = $this->image;
        }
        if ($this->selected === true) {
            $result['selected'] = true;
        }
        foreach ($this->nestedItems as $object) {
            $result['list'][] = $object;
        }
        return $result;
    }

    /**
     * @param ?SimpleXMLElement $xmlParent
     * @param bool $textAsAttribute
     * @param bool $encryptValue
     * @param string $nonce
     */
    public function getXMLElement(?SimpleXMLElement $xmlParent, bool $textAsAttribute = true, bool $encryptValue = false, string $nonce = ''): void
    {
        if ($xmlParent === null) {
            return;
        }
        if ($textAsAttribute === true) {
            $option = $xmlParent->addChild('option');
            SimpleXML::addAttribute($option, 'text', $this->text);
        } else {
            $option = SimpleXML::addChild($xmlParent, 'option', $this->text);
        }
        //TODO: pass cell nonce to this method, otherwise we won't be able to recreate client IDs on the server which might be needed for actions
        SimpleXML::addAttribute($option, 'value', Session::encrypt($this->value, $nonce));
        if ($this->selected === true) {
            $option?->addAttribute('selected', '1');
        }
        if (!empty($this->image)) {
            SimpleXML::addAttribute($option, 'img_src', $this->image);
            if (!isset($xmlParent->attributes()['comboType'])) {
                $xmlParent->addAttribute('comboType', 'image');
            }
        }
        foreach ($this->nestedItems as $nested) {
            if ($nested instanceof Proxy) {
                $nested->getXMLElement($option);
            }
        }
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }
}
