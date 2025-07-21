<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Form;

use byteShard\Internal\SimpleXML;
use SimpleXMLElement;

/**
 * Class Settings
 * @package byteShard\CellContent\Form
 */
class Settings implements FormSettingsInterface
{
    protected string              $inputHeight = 'auto';
    protected string              $inputWidth  = 'auto';
    protected Enum\Label\Align    $labelAlign  = Enum\Label\Align::LEFT;
    protected string              $labelHeight = 'auto';
    protected string              $labelWidth  = 'auto';
    protected string              $noteWidth   = 'auto';
    protected int                 $offsetLeft  = 0;
    protected int                 $offsetTop   = 0;
    protected Enum\Label\Position $position    = Enum\Label\Position::LEFT;

    /** @API */
    public function setInputHeight(?int $height): self
    {
        $this->inputHeight = $height === null ? 'auto' : (string)$height;
        return $this;
    }

    /** @API */
    public function setInputWidth(?int $width): self
    {
        $this->inputWidth = $width === null ? 'auto' : (string)$width;
        return $this;
    }

    /** @API */
    public function setLabelAlign(Enum\Label\Align $labelAlign): self
    {
        $this->labelAlign = $labelAlign;
        return $this;
    }

    /** @API */
    public function setLabelHeight(?int $height): self
    {
        $this->labelHeight = $height === null ? 'auto' : (string)$height;
        return $this;
    }

    /** @API */
    public function setLabelWidth(?int $width): self
    {
        $this->labelWidth = $width === null ? 'auto' : (string)$width;
        return $this;
    }

    /** @API */
    public function setNoteWidth(?int $width): self
    {
        $this->noteWidth = $width === null ? 'auto' : (string)$width;
        return $this;
    }

    /** @API */
    public function setOffsetLeft(?int $int): self
    {
        $this->offsetLeft = $int ?? 0;
        return $this;
    }

    /** @API */
    public function setOffsetTop(?int $int): self
    {
        $this->offsetTop = $int ?? 0;
        return $this;
    }

    /** @API */
    public function setPosition(Enum\Label\Position $position): self
    {
        $this->position = $position;
        return $this;
    }

    /** @API */
    public function getInputWidth(): string
    {
        return $this->inputWidth;
    }

    /** @API */
    public function getSettings(): array
    {
        $settings['type']        = 'settings';
        $settings['inputHeight'] = $this->inputHeight;
        $settings['inputWidth']  = $this->inputWidth;
        $settings['labelAlign']  = $this->labelAlign->value;
        $settings['labelHeight'] = $this->labelHeight;
        $settings['labelWidth']  = $this->labelWidth;
        $settings['noteWidth']   = $this->noteWidth;
        $settings['offsetLeft']  = $this->offsetLeft;
        $settings['offsetTop']   = $this->offsetTop;
        $settings['position']    = $this->position->value;
        return $settings;
    }

    /** @internal */
    public function getXMLElement(?SimpleXMLElement $xmlElement, bool $inputWidth = true): void
    {
        if ($xmlElement === null) {
            return;
        }
        $child_element = $xmlElement->addChild('item');
        SimpleXML::addAttribute($child_element, 'type', 'settings');
        SimpleXML::addAttribute($child_element, 'inputHeight', $this->inputHeight);
        SimpleXML::addAttribute($child_element, 'labelAlign', $this->labelAlign->value);
        SimpleXML::addAttribute($child_element, 'labelHeight', $this->labelHeight);
        SimpleXML::addAttribute($child_element, 'labelWidth', $this->labelWidth);
        SimpleXML::addAttribute($child_element, 'noteWidth', $this->noteWidth);
        SimpleXML::addAttribute($child_element, 'offsetLeft', (string)$this->offsetLeft);
        SimpleXML::addAttribute($child_element, 'offsetTop', (string)$this->offsetTop);
        SimpleXML::addAttribute($child_element, 'position', $this->position->value);
        if ($inputWidth === true) {
            SimpleXML::addAttribute($child_element, 'inputWidth', $this->inputWidth);
        }
    }
}
